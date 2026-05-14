<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Models\Service;
use App\Services\Mikrotik\Clients\SocketMikrotikApiClientFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnose and fix PPPoE username mismatches between DB and MikroTik routers.
 *
 * MikroTik routers may store usernames with different capitalization/prefix
 * than what's in the database (e.g., "ModeM-V2001" vs "kadus3-v2001").
 * This command identifies mismatches by matching numeric sequences, then
 * updates the database to match what MikroTik reports.
 *
 * Usage:
 *   php artisan mikrotik:fix-pppoe-usernames          # dry-run, preview changes
 *   php artisan mikrotik:fix-pppoe-usernames --apply # save changes to DB
 */
class FixPppoeUsernamePrefixCommand extends Command
{
    protected $signature = '
        mikrotik:fix-pppoe-usernames
        {--router= : Specific router ID}
        {--apply : Apply changes to database (default is dry-run)}
        {--force : Skip confirmation prompt when --apply is used}
    ';

    protected $description = 'Fix PPPoE username prefix mismatches between DB and MikroTik routers';

    /** @var array<int, array<string, mixed>> */
    private array $activeCache = [];

    public function handle(SocketMikrotikApiClientFactory $factory): int
    {
        $routerId = $this->option('router');
        $apply    = $this->option('apply');

        if (! $apply) {
            $this->info('Dry-run mode. Use <info>--apply</info> to save changes to the database.');
            $this->newLine();
        }

        $routers = Router::where('is_active', true)
            ->when($routerId, fn ($q) => $q->where('id', (int) $routerId))
            ->orderBy('name')
            ->get();

        if ($routers->isEmpty()) {
            $this->error('No active routers found.');
            return self::FAILURE;
        }

        $results = [];

        foreach ($routers as $router) {
            $results[] = $this->processRouter($router, $factory, $apply);
        }

        $this->newLine();
        $this->table(
            ['Router', 'Services', 'MikroTik Active', 'Matched', 'Updates Needed'],
            array_map(fn ($r) => [
                $r['router'],
                $r['services'],
                $r['active'],
                $r['matched'],
                $r['updates'],
            ], $results),
        );

        $totalUpdates = array_sum(array_column($results, 'updates'));
        $totalServices = array_sum(array_column($results, 'services'));

        $this->newLine();

        if ($apply) {
            $this->info("Done. {$totalUpdates} service(s) updated out of {$totalServices} total.");
        } else {
            $this->info("Dry-run complete. {$totalUpdates} service(s) would be updated.");
            $this->line('Run with <info>--apply</info> to save changes.');
        }

        return self::SUCCESS;
    }

    private function processRouter(Router $router, SocketMikrotikApiClientFactory $factory, bool $apply): array
    {
        $this->line("<comment>{$router->name}</comment> ({$router->host})");

        $client = app($factory::class)->forRouter($router);

        try {
            $this->activeCache = $client->print('/ppp/active', ['.id', 'name']);
        } catch (\Throwable $e) {
            $this->error("  ✗ Failed to connect: {$e->getMessage()}");
            $client->disconnect();
            return ['router' => $router->name, 'services' => 0, 'active' => 0, 'matched' => 0, 'updates' => 0];
        }

        $client->disconnect();

        $mikrotikLower = array_unique(array_map(
            fn ($n) => strtolower(trim((string) ($n['name'] ?? ''))),
            $this->activeCache,
        ));

        $services = Service::where('router_id', $router->id)
            ->whereNotNull('pppoe_username')
            ->where('pppoe_username', '!=', '')
            ->whereIn('overall_status', ['active', 'provisioning', 'isolated', 'suspended'])
            ->get(['id', 'pppoe_username', 'monitor_pppoe_username']);

        $updates   = [];
        $matched   = 0;
        $notFound  = 0;

        foreach ($services as $service) {
            $dbUser = strtolower(trim((string) ($service->monitor_pppoe_username ?: $service->pppoe_username)));

            if (in_array($dbUser, $mikrotikLower)) {
                $matched++;
                continue;
            }

            $mikrotikName = $this->findBestMatch($dbUser, $this->activeCache);

            if ($mikrotikName !== null) {
                $updates[] = [
                    'service_id'  => $service->id,
                    'old'         => $service->monitor_pppoe_username ?: $service->pppoe_username,
                    'new'         => $mikrotikName,
                    'db_lower'    => $dbUser,
                    'mikro_lower' => strtolower($mikrotikName),
                ];
            } else {
                $notFound++;
            }
        }

        $updatesCount = count($updates);

        $this->line(sprintf(
            '  Active: <info>%d</info> | Matched: <info>%d</info> | Not Found in MikroTik: <comment>%d</comment> | Need Update: <comment>%d</comment>',
            count($this->activeCache),
            $matched,
            $notFound,
            $updatesCount,
        ));

        if ($updatesCount > 0) {
            $this->line('  Samples (DB → MikroTik):');
            foreach (array_slice($updates, 0, 3) as $u) {
                $this->line("    <comment>{$u['old']}</comment> → <info>{$u['new']}</info>");
            }
        }

        $appliedCount = 0;

        if ($apply && $updatesCount > 0) {
            $appliedCount = $this->applyUpdates($router, $updates);
            $this->line("  <info>✓ {$appliedCount} service(s) updated</info>");
        } elseif ($updatesCount > 0) {
            $this->line("  <comment>Would update {$updatesCount} service(s) with --apply</comment>");
        } else {
            $this->line('  <info>✓ No changes needed</info>');
        }

        return [
            'router'   => $router->name,
            'services' => $services->count(),
            'active'   => count($this->activeCache),
            'matched'  => $matched,
            'updates'  => $apply ? $appliedCount : $updatesCount,
        ];
    }

    /**
     * Find the best matching MikroTik username using two-pass matching:
     * 1. Exact normalized match (strip non-alphanumeric, lowercase)
     *    Handles: "WIRUN_V3000" → "WIRUN_V3000"
     * 2. Last number sequence match — handles prefix-only differences
     *    e.g., "kadus3-v2001" → "ModeM-V2001"  (both have last num = 2001)
     *          "modem-001"    → "ModeM-001"    (both have last num = "001")
     *
     * Digit count of the last number must also match to avoid false matches
     * between "modem-1" and "modem-001".
     */
    private function findBestMatch(string $dbUsernameLower, array $activeSessions): ?string
    {
        $dbStripped = preg_replace('/[^a-z0-9]/', '', $dbUsernameLower);
        preg_match_all('/\d+/', $dbStripped, $dbNums);
        $dbNums = $dbNums[0] ?? [];

        // Pass 1: exact strip match (fast, handles exact matches)
        foreach ($activeSessions as $session) {
            $mikroName = (string) ($session['name'] ?? '');
            if ($mikroName === '') {
                continue;
            }

            if (preg_replace('/[^a-z0-9]/', '', $dbUsernameLower)
                === preg_replace('/[^a-z0-9]/', '', strtolower($mikroName))) {
                return $mikroName;
            }
        }

        // Pass 2: match by last number sequence (ignores prefix differences)
        if ($dbNums === []) {
            return null;
        }

        $dbLastNum = end($dbNums);
        $dbLastNumLen = strlen($dbLastNum);

        foreach ($activeSessions as $session) {
            $mikroName = (string) ($session['name'] ?? '');
            if ($mikroName === '') {
                continue;
            }

            $mikroStripped = preg_replace('/[^a-z0-9]/', '', strtolower($mikroName));
            preg_match_all('/\d+/', $mikroStripped, $mikroNums);
            $mikroNums = $mikroNums[0] ?? [];

            if ($mikroNums === []) {
                continue;
            }

            $mikroLastNum = end($mikroNums);
            $mikroLastNumLen = strlen($mikroLastNum);

            // Last number must match exactly (same digits) to avoid false matches
            // e.g., "modem-001" (DB) should match "ModeM-001" (MikroTik)
            // but NOT "modem-1" (different router)
            if ($dbLastNum === $mikroLastNum && $dbLastNumLen === $mikroLastNumLen) {
                return $mikroName;
            }
        }

        return null;
    }

    private function applyUpdates(Router $router, array $updates): int
    {
        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                'Update ' . count($updates) . " service(s) on {$router->name}? "
                . 'pppoe_username will be changed to match MikroTik active sessions.'
            );
            if (! $confirmed) {
                $this->line('  Skipped by user.');
                return 0;
            }
        }

        // Build router identifier for collision-safe prefixes (short router code)
        $routerPrefix = $this->routerPrefix($router);

        $count = 0;
        $skipped = 0;

        DB::transaction(function () use ($updates, &$count, &$skipped, $routerPrefix, $router): void {
            foreach ($updates as $u) {
                $newUsername = $u['new'];

                // Check for global duplicate BEFORE updating
                $conflict = Service::query()
                    ->where('id', '!=', $u['service_id'])
                    ->where('monitor_pppoe_username', $newUsername)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($conflict) {
                    // Another router already uses this username — add router prefix to avoid collision.
                    // e.g., "ModeM-V2001" (used on router 108) → "OLT3-ModeM-V2001"
                    $conflictUsername = $newUsername;
                    $newUsername = "{$routerPrefix}-{$conflictUsername}";

                    // Double-check the prefixed username doesn't also conflict
                    $stillConflict = Service::query()
                        ->where('id', '!=', $u['service_id'])
                        ->where('monitor_pppoe_username', $newUsername)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($stillConflict) {
                        $this->warn("  Cannot update service {$u['service_id']}: '{$conflictUsername}' also exists on this router as '{$newUsername}'. Manual fix required.");
                        $skipped++;
                        continue;
                    }
                }

                $mikrotikActual = $u['new'];

                Service::query()->where('id', $u['service_id'])->update([
                    'pppoe_username' => $newUsername,
                    // Leave monitor_pppoe_username as NULL so the sync service uses
                    // the number-suffix fallback matching against MikroTik active sessions.
                    // This avoids global unique constraint conflicts when multiple routers
                    // share the same V-number (e.g., OLT Kadus 3 and OLT Sela 2 both use ModeM-V2XXX).
                ]);

                if ($newUsername !== $mikrotikActual) {
                    $this->line("  <comment>{$u['old']}</comment> → pppoe: <info>{$newUsername}</info> (monitor fallback: V-number matching)");
                }

                $count++;
            }
        });

        if ($skipped > 0) {
            $this->warn("  {$skipped} service(s) skipped due to cross-router duplicate conflict.");
        }

        return $count;
    }

    private function routerPrefix(Router $router): string
    {
        // Build a short, router-specific prefix for collision avoidance.
        // e.g., "OLT Kadus 1" → "OLT1", "OLT Kadus 2" → "OLT2", "OLT Sela 2" → "Sela2"
        $name = preg_replace('/[^a-zA-Z0-9]/', '', $router->name);

        // Collapse consecutive capitals into single letters: "OLTKadus1" → "OLT1"
        $short = preg_replace('/([A-Z])[a-z]+/', '$1', $name);
        $short = substr($short, 0, 6); // max 6 chars

        // Fallback: use router_code stripped of RTR- prefix
        if (strlen($short) < 2) {
            $short = str_replace(['RTR-', 'RTR', '-', '_'], '', strtoupper($router->router_code ?? $router->name));
            $short = substr($short, 0, 6);
        }

        return $short ?: 'R' . $router->id;
    }
}