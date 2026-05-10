<?php

namespace App\Console\Commands;

use App\Jobs\SyncPppoeMonitorJob;
use App\Models\Router;
use App\Services\Monitoring\PppoeMonitorSyncService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Console\Command;
use Throwable;

class SyncPppoeMonitorCommand extends Command
{
    use InteractsWithAutomationLogging;

    protected $signature = 'monitor:sync-pppoe
        {router? : Optional router ID to synchronize}
        {--chunk= : Number of services to process per database chunk}
        {--queue : Dispatch queued sync job(s) instead of running inline}';

    protected $description = 'Synchronize ONT monitoring status from PPPoE monitor sessions.';

    public function handle(PppoeMonitorSyncService $syncService): int
    {
        $chunkSize = max(1, (int) ($this->option('chunk') ?: config('automation.monitoring.pppoe.chunk', 200)));
        $routers = $this->resolveRouters();

        if ($routers->isEmpty()) {
            $this->warn('No routers matched the synchronization target.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('queue')) {
            foreach ($routers as $router) {
                SyncPppoeMonitorJob::dispatch($router->id, $chunkSize);
            }

            $this->info(sprintf('Queued %d PPPoE monitor sync job(s).', $routers->count()));

            return self::SUCCESS;
        }

        $failedRouters = 0;

        foreach ($routers as $router) {
            try {
                $summary = $syncService->syncRouter($router, $chunkSize);
            } catch (Throwable $throwable) {
                report($throwable);
                $failedRouters++;
                $this->logAutomationFailed('monitoring.sync_pppoe.command', $throwable, [
                    'router_id' => $router->id,
                    'router_code' => $router->router_code,
                    'chunk_size' => $chunkSize,
                ]);

                $this->error(sprintf(
                    'Router %s (%s) failed: %s',
                    $router->router_code,
                    $router->router_name,
                    $throwable->getMessage(),
                ));

                continue;
            }

            $this->info(sprintf(
                'Router %s (%s): checked %d service(s), online %d, offline %d, overall updated %d, history logged %d.',
                $summary['router_code'],
                $summary['router_name'],
                $summary['services_checked'],
                $summary['online_count'],
                $summary['offline_count'],
                $summary['overall_status_updates'],
                $summary['history_logs_created'],
            ));
        }

        return $failedRouters === 0
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function resolveRouters()
    {
        if ($this->argument('router') !== null) {
            $router = Router::query()->find($this->argument('router'));

            if ($router === null) {
                $this->error('Router not found.');

                return collect();
            }

            return collect([$router]);
        }

        return Router::query()
            ->where('is_active', true)
            ->whereHas('services', function ($query): void {
                $query->where(function ($q): void {
                    $q->where(function ($inner): void {
                        $inner->whereNotNull('monitor_pppoe_username')
                              ->where('monitor_pppoe_username', '!=', '');
                    })->orWhere(function ($inner): void {
                        $inner->whereNotNull('pppoe_username')
                              ->where('pppoe_username', '!=', '');
                    });
                });
            })
            ->orderBy('router_code')
            ->get();
    }
}
