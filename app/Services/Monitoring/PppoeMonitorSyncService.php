<?php

namespace App\Services\Monitoring;

use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Contracts\Mikrotik\RouterOsCommandAdapter;
use App\Enums\MonitorPppoeStatus;
use App\Enums\ServiceOverallStatus;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceMonitorPppoeLog;
use App\Models\ServiceMonitorPppoeStatus;
use App\Services\Mikrotik\RouterOsCommandAdapterResolver;
use App\Services\Provisioning\ServiceOverallStateManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PppoeMonitorSyncService
{
    public function __construct(
        private readonly MikrotikApiClientFactory $clientFactory,
        private readonly ServiceOverallStateManager $overallStateManager,
        private readonly RouterOsCommandAdapterResolver $commandAdapter,
    ) {}

    public function syncRouter(Router $router, int $chunkSize = 200): array
    {
        $router = Router::query()->findOrFail($router->getKey());
        $syncedAt = now();
        $sessionsByUsername = $this->fetchActiveSessionsByUsername($router);
        $summary = [
            'router_id' => $router->id,
            'router_code' => $router->router_code,
            'router_name' => $router->router_name,
            'services_checked' => 0,
            'online_count' => 0,
            'offline_count' => 0,
            'overall_status_updates' => 0,
            'history_logs_created' => 0,
        ];

        Service::query()
            ->where('router_id', $router->id)
            ->where(function ($q): void {
                $q->where(function ($inner): void {
                    $inner->whereNotNull('pppoe_username')
                          ->where('pppoe_username', '!=', '');
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('monitor_pppoe_username')
                          ->where('monitor_pppoe_username', '!=', '');
                });
            })
            ->whereIn('overall_status', [
                ServiceOverallStatus::Active->value,
                ServiceOverallStatus::Provisioning->value,
                ServiceOverallStatus::Isolated->value,
                ServiceOverallStatus::Suspended->value,
            ])
            ->select('id')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($services) use (&$summary, $router, $sessionsByUsername, $syncedAt): void {
                foreach ($services as $serviceReference) {
                    $result = $this->syncSingleService(
                        serviceId: (int) $serviceReference->id,
                        router: $router,
                        sessionsByUsername: $sessionsByUsername,
                        syncedAt: $syncedAt,
                    );

                    if (($result['processed'] ?? false) !== true) {
                        continue;
                    }

                    $summary['services_checked']++;

                    if (($result['status'] ?? null) === MonitorPppoeStatus::Online) {
                        $summary['online_count']++;
                    } else {
                        $summary['offline_count']++;
                    }

                    if (($result['overall_status_updated'] ?? false) === true) {
                        $summary['overall_status_updates']++;
                    }

                    if (($result['history_logged'] ?? false) === true) {
                        $summary['history_logs_created']++;
                    }
                }
            });

        return $summary;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function fetchActiveSessionsByUsername(Router $router): array
    {
        $client = $this->clientFactory->forRouter($router);
        $adapter = $this->commandAdapter->resolve($router);

        try {
            $records = $client->print(
                $adapter->pppoeActivePath(),
                $adapter->pppoeActiveProperties(),
            );
        } finally {
            $client->disconnect();
        }

        $sessionsByUsername = [];

        foreach ($records as $record) {
            $username = $this->normalizeUsername($record['name'] ?? null);

            if ($username === null || array_key_exists($username, $sessionsByUsername)) {
                continue;
            }

            $sessionsByUsername[$username] = $record;
        }

        return $sessionsByUsername;
    }

    /**
     * @param  array<string, array<string, string>>  $sessionsByUsername
     * @return array{processed:bool,status?:MonitorPppoeStatus,overall_status_updated?:bool,history_logged?:bool}
     */
    private function syncSingleService(
        int $serviceId,
        Router $router,
        array $sessionsByUsername,
        CarbonInterface $syncedAt,
    ): array {
        return DB::transaction(function () use ($serviceId, $router, $sessionsByUsername, $syncedAt): array {
            $service = Service::query()
                ->with('pppoeMonitorStatus')
                ->lockForUpdate()
                ->find($serviceId);

            if ($service === null || (int) $service->router_id !== (int) $router->id) {
                return [
                    'processed' => false,
                ];
            }

            $username = $this->normalizeUsername($service->monitor_pppoe_username)
                ?: $this->normalizeUsername($service->pppoe_username);

            if ($username === null) {
                return [
                    'processed' => false,
                ];
            }

            $session = $sessionsByUsername[$username] ?? null;

            // Fallback: if no exact username match, try to match by extracting the last
            // number sequence from pppoe_username. This handles the case where MikroTik
            // uses a different prefix than the billing DB (e.g., DB has "OLTK3-ModeM-V2001"
            // but MikroTik returns "ModeM-V2001" — both have last number 2001).
            if ($session === null && $service->monitor_pppoe_username === null) {
                $session = $this->findSessionByNumberSuffix($sessionsByUsername, $service->pppoe_username);
            }
            $nextStatus = $session === null
                ? MonitorPppoeStatus::Offline
                : MonitorPppoeStatus::Online;
            $sessionInfo = $session === null ? null : $this->extractSessionInfo($session);
            $monitorSnapshot = $service->pppoeMonitorStatus;
            $previousStatus = $monitorSnapshot?->status;
            $desiredOverallStatus = $this->overallStateManager->desiredStatus($service, $monitorSnapshot);
            $effectiveOverallStatus = $this->overallStateManager->effectiveStatus($desiredOverallStatus, $nextStatus);
            $snapshot = $monitorSnapshot ?? new ServiceMonitorPppoeStatus([
                'service_id' => $service->id,
            ]);

            $snapshot->fill([
                'router_id' => $router->id,
                'monitor_pppoe_username' => $username,
                'status' => $nextStatus->value,
                'base_overall_status' => $desiredOverallStatus->value,
                'last_seen_at' => $nextStatus === MonitorPppoeStatus::Online
                    ? $syncedAt
                    : $monitorSnapshot?->last_seen_at,
                'last_synced_at' => $syncedAt,
                'session_info' => $sessionInfo,
            ]);

            $statusChanged = $previousStatus !== $nextStatus;

            if ($monitorSnapshot === null || $statusChanged) {
                $snapshot->last_status_changed_at = $syncedAt;
            }

            if (! $snapshot->exists || $snapshot->isDirty()) {
                $snapshot->save();
            }

            $overallStatusUpdated = false;
            $currentOverallStatus = $service->overall_status instanceof ServiceOverallStatus
                ? $service->overall_status
                : ServiceOverallStatus::from((string) $service->overall_status);

            if ($currentOverallStatus !== $effectiveOverallStatus) {
                $service->update([
                    'overall_status' => $effectiveOverallStatus->value,
                ]);

                $overallStatusUpdated = true;
            }

            $historyLogged = $monitorSnapshot === null || $statusChanged;

            if ($historyLogged) {
                ServiceMonitorPppoeLog::query()->create([
                    'service_id' => $service->id,
                    'router_id' => $router->id,
                    'monitor_pppoe_username' => $username,
                    'previous_status' => $previousStatus?->value,
                    'status' => $nextStatus->value,
                    'last_seen_at' => $snapshot->last_seen_at,
                    'session_info' => $sessionInfo,
                    'observed_at' => $syncedAt,
                ]);
            }

            return [
                'processed' => true,
                'status' => $nextStatus,
                'overall_status_updated' => $overallStatusUpdated,
                'history_logged' => $historyLogged,
            ];
        });
    }

    /**
     * @param  array<string, string>  $session
     * @return array<string, string>|null
     */
    private function extractSessionInfo(array $session): ?array
    {
        $sessionInfo = array_filter([
            'remote_id' => $this->normalizeNullableString($session['.id'] ?? null),
            'service' => $this->normalizeNullableString($session['service'] ?? null),
            'caller_id' => $this->normalizeNullableString($session['caller-id'] ?? null),
            'address' => $this->normalizeNullableString($session['address'] ?? null),
            'uptime' => $this->normalizeNullableString($session['uptime'] ?? null),
            'session_id' => $this->normalizeNullableString($session['session-id'] ?? null),
        ], static fn (?string $value): bool => $value !== null);

        return $sessionInfo !== [] ? $sessionInfo : null;
    }

    private function normalizeUsername(?string $username): ?string
    {
        if ($username === null) {
            return null;
        }

        $username = strtolower(trim($username));

        return $username !== ''
            ? $username
            : null;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }

    /**
     * Find a MikroTik PPP active session by matching the last number sequence
     * extracted from the DB username. This handles prefix differences between
     * the billing DB and MikroTik (e.g., "OLTK3-ModeM-V2001" matches "ModeM-V2001"
     * when both share the same last number "2001").
     *
     * @param  array<string, array<string, string>>  $sessionsByUsername
     */
    private function findSessionByNumberSuffix(array $sessionsByUsername, ?string $dbUsername): ?array
    {
        if ($dbUsername === null) {
            return null;
        }

        $dbStripped = preg_replace('/[^a-z0-9]/', '', strtolower(trim($dbUsername)));
        preg_match_all('/\d+/', $dbStripped, $dbNums);
        $dbNums = $dbNums[0] ?? [];

        if ($dbNums === []) {
            return null;
        }

        $dbLastNum = end($dbNums);
        $dbLastNumLen = strlen($dbLastNum);

        foreach ($sessionsByUsername as $sessionUsername => $session) {
            $sessionStripped = preg_replace('/[^a-z0-9]/', '', $sessionUsername);
            preg_match_all('/\d+/', $sessionStripped, $sessionNums);
            $sessionNums = $sessionNums[0] ?? [];

            if ($sessionNums === []) {
                continue;
            }

            $sessionLastNum = end($sessionNums);
            $sessionLastNumLen = strlen($sessionLastNum);

            if ($dbLastNum === $sessionLastNum && $dbLastNumLen === $sessionLastNumLen) {
                return $session;
            }
        }

        return null;
    }
}
