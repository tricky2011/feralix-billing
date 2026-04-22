<?php

namespace App\Services\Mikrotik;

use App\Actions\Mikrotik\DetectMikrotikVidConflictAction;
use App\Data\Mikrotik\MikrotikVidInventoryRecord;
use App\Enums\MikrotikSyncJobStatus;
use App\Enums\MikrotikSyncJobType;
use App\Enums\MikrotikSyncVidActionType;
use App\Enums\VidStatus;
use App\Enums\VidType;
use App\Models\MikrotikSyncJob;
use App\Models\MikrotikSyncVidLog;
use App\Models\Router;
use App\Models\Service;
use App\Models\Vid;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class MikrotikVidSyncService
{
    public function __construct(
        private readonly MikrotikVidInventoryProviderResolver $providerResolver,
        private readonly DetectMikrotikVidConflictAction $detectConflict,
    ) {}

    public function sync(Router $router, ?string $providerName = null): MikrotikSyncJob
    {
        $router = Router::query()->findOrFail($router->getKey());
        $provider = $this->providerResolver->resolve($providerName);

        $job = MikrotikSyncJob::query()->create([
            'router_id' => $router->id,
            'job_type' => MikrotikSyncJobType::VidInventory->value,
            'job_status' => MikrotikSyncJobStatus::Running->value,
            'started_at' => now(),
            'total_found' => 0,
            'total_new' => 0,
            'total_updated' => 0,
            'total_conflict' => 0,
            'summary' => null,
        ]);

        $totals = [
            'total_found' => 0,
            'total_new' => 0,
            'total_updated' => 0,
            'total_conflict' => 0,
        ];
        $totalUnchanged = 0;
        $totalFailed = 0;
        $syncedAt = now();
        $scopes = $router->scopes()
            ->orderBy('vid_start')
            ->orderBy('id')
            ->get();

        try {
            $records = $provider->fetchVidInventory($router);

            foreach ($records as $record) {
                $totals['total_found']++;

                try {
                    $action = DB::transaction(
                        fn (): MikrotikSyncVidActionType => $this->syncSingleRecord($job, $router, $scopes, $record, $syncedAt, $provider->name())
                    );

                    match ($action) {
                        MikrotikSyncVidActionType::Created => ++$totals['total_new'],
                        MikrotikSyncVidActionType::Updated => ++$totals['total_updated'],
                        MikrotikSyncVidActionType::Conflict => ++$totals['total_conflict'],
                        MikrotikSyncVidActionType::Unchanged => ++$totalUnchanged,
                        MikrotikSyncVidActionType::Failed => ++$totalFailed,
                    };
                } catch (Throwable $throwable) {
                    report($throwable);
                    $totalFailed++;

                    $this->storeLog(
                        job: $job,
                        router: $router,
                        record: $record,
                        actionType: MikrotikSyncVidActionType::Failed,
                        localStatusBefore: null,
                        localStatusAfter: null,
                        conflictFlag: null,
                        message: sprintf('Sync failed for VID %d: %s', $record->vid, $throwable->getMessage()),
                        createdAt: $syncedAt,
                    );
                }
            }

            $jobStatus = MikrotikSyncJobStatus::Completed;
            $summary = $this->buildSummary($router, $totals, $totalUnchanged, $totalFailed, $provider->name());
        } catch (Throwable $throwable) {
            report($throwable);

            $jobStatus = MikrotikSyncJobStatus::Failed;
            $summary = sprintf(
                'Provider %s failed for router %s: %s',
                $provider->name(),
                $router->router_code,
                $throwable->getMessage(),
            );
        }

        $job->update([
            'job_status' => $jobStatus->value,
            'finished_at' => now(),
            'total_found' => $totals['total_found'],
            'total_new' => $totals['total_new'],
            'total_updated' => $totals['total_updated'],
            'total_conflict' => $totals['total_conflict'],
            'summary' => $summary,
        ]);

        return $job->refresh()->load('router:id,router_code,router_name');
    }

    private function syncSingleRecord(
        MikrotikSyncJob $job,
        Router $router,
        Collection $scopes,
        MikrotikVidInventoryRecord $record,
        CarbonInterface $syncedAt,
        string $providerName,
    ): MikrotikSyncVidActionType {
        $existingVid = Vid::query()
            ->where('router_id', $router->id)
            ->where('vid', $record->vid)
            ->lockForUpdate()
            ->first();

        $localStatusBefore = $existingVid?->status?->value;

        if ($existingVid === null) {
            $createdVid = Vid::query()->create([
                ...$this->buildPayload($router, $scopes, $record, null, null),
                'sync_source' => $this->syncSource($providerName),
                'last_synced_at' => $syncedAt,
            ]);

            $this->storeLog(
                job: $job,
                router: $router,
                record: $record,
                actionType: MikrotikSyncVidActionType::Created,
                localStatusBefore: null,
                localStatusAfter: $createdVid->status?->value,
                conflictFlag: false,
                message: sprintf('VID %d created from %s provider.', $record->vid, $providerName),
                createdAt: $syncedAt,
            );

            return MikrotikSyncVidActionType::Created;
        }

        $activeService = $this->resolveActiveService($existingVid);

        if ($activeService !== null) {
            $mismatches = $this->detectConflict->execute($existingVid, $record);

            if ($mismatches !== []) {
                $existingVid->fill([
                    'scope_id' => $this->resolveScopeId($scopes, $record->vid),
                    'sync_source' => $this->syncSource($providerName),
                    'last_synced_at' => $syncedAt,
                ]);

                if ($existingVid->isDirty()) {
                    $existingVid->save();
                }

                $this->storeLog(
                    job: $job,
                    router: $router,
                    record: $record,
                    actionType: MikrotikSyncVidActionType::Conflict,
                    localStatusBefore: $localStatusBefore,
                    localStatusAfter: $existingVid->status?->value,
                    conflictFlag: true,
                    message: $this->buildConflictMessage($activeService, $mismatches),
                    createdAt: $syncedAt,
                );

                return MikrotikSyncVidActionType::Conflict;
            }
        }

        $payload = $this->buildPayload($router, $scopes, $record, $existingVid, $activeService);
        $changedAttributes = $this->extractChangedAttributes($existingVid, $payload);

        $existingVid->fill([
            ...$payload,
            'sync_source' => $this->syncSource($providerName),
            'last_synced_at' => $syncedAt,
        ]);

        if ($existingVid->isDirty()) {
            $existingVid->save();
        }

        $actionType = $changedAttributes === []
            ? MikrotikSyncVidActionType::Unchanged
            : MikrotikSyncVidActionType::Updated;

        $this->storeLog(
            job: $job,
            router: $router,
            record: $record,
            actionType: $actionType,
            localStatusBefore: $localStatusBefore,
            localStatusAfter: $existingVid->status?->value,
            conflictFlag: false,
            message: $actionType === MikrotikSyncVidActionType::Updated
                ? sprintf('VID %d updated automatically from %s provider.', $record->vid, $providerName)
                : sprintf('VID %d already matches %s provider data.', $record->vid, $providerName),
            createdAt: $syncedAt,
        );

        return $actionType;
    }

    private function buildPayload(
        Router $router,
        Collection $scopes,
        MikrotikVidInventoryRecord $record,
        ?Vid $existingVid,
        ?Service $activeService,
    ): array {
        $status = $activeService !== null
            ? VidStatus::Assigned
            : $record->inferredStatus();

        return [
            'router_id' => $router->id,
            'scope_id' => $this->resolveScopeId($scopes, $record->vid),
            'vid' => $record->vid,
            'vid_type' => $existingVid?->vid_type?->value ?? VidType::CustomerInternet->value,
            'subnet_cidr' => $record->subnetCidr,
            'gateway_ip' => $record->gatewayIp,
            'pool_start_ip' => $record->poolStartIp,
            'pool_end_ip' => $record->poolEndIp,
            'pool_ip_count' => $this->resolvePoolIpCount($record),
            'rate_limit_mbps' => $existingVid?->rate_limit_mbps ?? 10,
            'status' => $status->value,
            'customer_id' => $activeService?->customer_id,
            'service_id' => $activeService?->id,
        ];
    }

    private function resolveScopeId(Collection $scopes, int $vid): ?int
    {
        $scope = $scopes->first(
            static fn ($routerScope): bool => $vid >= $routerScope->vid_start && $vid <= $routerScope->vid_end
        );

        return $scope?->id;
    }

    private function resolvePoolIpCount(MikrotikVidInventoryRecord $record): int
    {
        if ($record->poolIpCount !== null && $record->poolIpCount >= 0) {
            return $record->poolIpCount;
        }

        if ($record->poolStartIp === null || $record->poolEndIp === null) {
            return 0;
        }

        $start = ip2long($record->poolStartIp);
        $end = ip2long($record->poolEndIp);

        if ($start === false || $end === false || $end < $start) {
            return 0;
        }

        return (int) ($end - $start + 1);
    }

    private function resolveActiveService(Vid $vid): ?Service
    {
        if ($vid->service_id !== null) {
            $service = Service::query()
                ->select(['id', 'customer_id', 'service_code', 'router_id', 'vid_id', 'internet_vid', 'overall_status'])
                ->active()
                ->find($vid->service_id);

            if ($service !== null) {
                return $service;
            }
        }

        return Service::query()
            ->select(['id', 'customer_id', 'service_code', 'router_id', 'vid_id', 'internet_vid', 'overall_status'])
            ->active()
            ->where(function ($builder) use ($vid): void {
                $builder
                    ->where('vid_id', $vid->id)
                    ->orWhere(function ($nested) use ($vid): void {
                        $nested
                            ->where('router_id', $vid->router_id)
                            ->where('internet_vid', $vid->vid);
                    });
            })
            ->first();
    }

    private function extractChangedAttributes(Vid $existingVid, array $payload): array
    {
        $changes = [];

        foreach ($payload as $attribute => $value) {
            $currentValue = match ($attribute) {
                'vid_type' => $existingVid->vid_type?->value,
                'status' => $existingVid->status?->value,
                default => $existingVid->getAttribute($attribute),
            };

            if ($currentValue === $value) {
                continue;
            }

            $changes[$attribute] = [
                'before' => $currentValue,
                'after' => $value,
            ];
        }

        return $changes;
    }

    private function buildConflictMessage(Service $service, array $mismatches): string
    {
        $details = collect($mismatches)
            ->map(function (array $values, string $field): string {
                return sprintf(
                    '%s(local=%s, remote=%s)',
                    $field,
                    $this->stringifyValue($values['local'] ?? null),
                    $this->stringifyValue($values['remote'] ?? null),
                );
            })
            ->implode('; ');

        return sprintf(
            'Conflict detected for active service %s (#%d): %s',
            $service->service_code,
            $service->id,
            $details,
        );
    }

    private function buildSummary(Router $router, array $totals, int $totalUnchanged, int $totalFailed, string $providerName): string
    {
        return sprintf(
            'Provider %s processed %d VID(s) for router %s: %d new, %d updated, %d conflict, %d unchanged, %d failed.',
            $providerName,
            $totals['total_found'],
            $router->router_code,
            $totals['total_new'],
            $totals['total_updated'],
            $totals['total_conflict'],
            $totalUnchanged,
            $totalFailed,
        );
    }

    private function storeLog(
        MikrotikSyncJob $job,
        Router $router,
        MikrotikVidInventoryRecord $record,
        MikrotikSyncVidActionType $actionType,
        ?string $localStatusBefore,
        ?string $localStatusAfter,
        ?bool $conflictFlag,
        ?string $message,
        CarbonInterface $createdAt,
    ): void {
        MikrotikSyncVidLog::query()->create([
            'sync_job_id' => $job->id,
            'router_id' => $router->id,
            'vid' => $record->vid,
            'action_type' => $actionType->value,
            'remote_vlan_name' => $record->vlanName,
            'remote_subnet_cidr' => $record->subnetCidr,
            'remote_pool_range' => $record->poolRange(),
            'local_status_before' => $localStatusBefore,
            'local_status_after' => $localStatusAfter,
            'conflict_flag' => $conflictFlag,
            'message' => $message,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function syncSource(string $providerName): string
    {
        return 'mikrotik:'.$providerName;
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
