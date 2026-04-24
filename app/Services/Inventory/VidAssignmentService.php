<?php

namespace App\Services\Inventory;

use App\Enums\VidStatus;
use App\Enums\VidType;
use App\Models\Service;
use App\Models\Vid;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VidAssignmentService
{
    public const ISSUE_SERVICE_WITHOUT_VID = 'service_without_vid';
    public const ISSUE_ASSIGNED_VID_WITHOUT_SERVICE = 'assigned_vid_without_service';
    public const ISSUE_SERVICE_VID_MISMATCH = 'service_vid_mismatch';
    public const ISSUE_DUPLICATE_ACTIVE_VID = 'duplicate_active_vid';
    public const ISSUE_DUPLICATE_ACTIVE_ROUTER_VID = 'duplicate_active_router_vid';

    public function syncService(Service $service, ?Vid $vid = null): void
    {
        $vid ??= $this->lockServiceVid($service);

        if (! $service->usesDedicatedResources()) {
            $this->releaseFromService($service, $vid);

            return;
        }

        $this->assignToService($service, $vid);
    }

    public function assignToService(Service $service, ?Vid $vid): void
    {
        if (! $service->usesDedicatedResources()) {
            $this->releaseFromService($service, $vid);

            return;
        }

        if ($vid === null) {
            throw ValidationException::withMessages([
                'vid_id' => 'An active service must have a dedicated customer internet VID.',
            ]);
        }

        $this->ensureVidMatchesService($service, $vid);

        $conflictingService = $this->activeServiceUsingVid($vid, $service->id);

        if ($conflictingService !== null) {
            throw ValidationException::withMessages([
                'vid_id' => sprintf(
                    'The selected VID is already assigned to active service %s.',
                    $conflictingService->service_code
                ),
            ]);
        }

        $assignedService = $this->assignedService($vid);

        if (
            $assignedService !== null
            && (int) $assignedService->id !== (int) $service->id
            && ! $assignedService->trashed()
            && $assignedService->usesDedicatedResources()
        ) {
            throw ValidationException::withMessages([
                'vid_id' => sprintf(
                    'The selected VID is already owned by active service %s.',
                    $assignedService->service_code
                ),
            ]);
        }

        $vid->forceFill([
            'status' => VidStatus::Assigned->value,
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
        ])->save();
    }

    public function releaseFromService(Service $service, ?Vid $vid = null): void
    {
        $vid ??= $this->lockServiceVid($service);

        if ($vid === null || (int) ($vid->service_id ?? 0) !== (int) $service->id) {
            return;
        }

        $otherActiveServices = $this->activeServicesUsingVid($vid, $service->id);

        if ($otherActiveServices->count() === 1) {
            $replacementService = $otherActiveServices->first();

            $vid->forceFill([
                'status' => VidStatus::Assigned->value,
                'customer_id' => $replacementService->customer_id,
                'service_id' => $replacementService->id,
            ])->save();

            return;
        }

        if ($otherActiveServices->count() > 1) {
            return;
        }

        $vid->forceFill([
            'status' => VidStatus::Available->value,
            'customer_id' => null,
            'service_id' => null,
        ])->save();
    }

    public function activeServiceUsingVid(Vid $vid, ?int $exceptServiceId = null): ?Service
    {
        return $this->activeServicesUsingVid($vid, $exceptServiceId)->first();
    }

    /**
     * @return Collection<int, Service>
     */
    public function activeServicesUsingVid(Vid $vid, ?int $exceptServiceId = null): Collection
    {
        return Service::query()
            ->select(['id', 'customer_id', 'service_code', 'router_id', 'vid_id', 'internet_vid', 'overall_status'])
            ->active()
            ->where(function ($query) use ($vid): void {
                $query
                    ->where('vid_id', $vid->id)
                    ->orWhere(function ($nested) use ($vid): void {
                        $nested
                            ->where('router_id', $vid->router_id)
                            ->where('internet_vid', $vid->vid);
                    });
            })
            ->when($exceptServiceId !== null, fn ($query) => $query->whereKeyNot($exceptServiceId))
            ->orderBy('id')
            ->get();
    }

    public function audit(): array
    {
        $issues = $this->emptyIssueBuckets();
        $activeServices = Service::query()
            ->active()
            ->with('vid')
            ->orderBy('id')
            ->get();

        foreach ($activeServices as $service) {
            if ($service->vid_id === null) {
                $issues[self::ISSUE_SERVICE_WITHOUT_VID][] = $this->serviceReference($service);

                continue;
            }

            $vid = $service->vid;

            if ($vid === null) {
                $issues[self::ISSUE_SERVICE_VID_MISMATCH][] = [
                    ...$this->serviceReference($service),
                    'vid_id' => $service->vid_id,
                    'mismatches' => ['missing_vid_record'],
                ];

                continue;
            }

            $mismatches = $this->mismatchesForServiceVid($service, $vid);

            if ($mismatches !== []) {
                $issues[self::ISSUE_SERVICE_VID_MISMATCH][] = [
                    ...$this->serviceReference($service),
                    'vid_id' => $vid->id,
                    'vid' => $vid->vid,
                    'mismatches' => $mismatches,
                ];
            }
        }

        $this->appendAssignedVidIssues($issues);
        $this->appendDuplicateIssues($issues, $activeServices);

        return [
            'summary' => $this->summarizeIssues($issues),
            'issues' => $issues,
        ];
    }

    public function repair(): array
    {
        return DB::transaction(function (): array {
            $before = $this->audit();
            $repairSummary = [
                'synced_services' => 0,
                'released_vids' => 0,
                'skipped_conflicts' => 0,
            ];

            Vid::query()
                ->where(function ($query): void {
                    $query
                        ->where('status', VidStatus::Assigned->value)
                        ->orWhereNotNull('service_id');
                })
                ->lockForUpdate()
                ->orderBy('id')
                ->get()
                ->each(function (Vid $vid) use (&$repairSummary): void {
                    $activeServices = $this->activeServicesUsingVid($vid);

                    if ($activeServices->count() > 1) {
                        $repairSummary['skipped_conflicts']++;

                        return;
                    }

                    if ($activeServices->count() === 1) {
                        $before = $this->vidOwnershipSnapshot($vid);
                        $this->assignToService($activeServices->first(), $vid);

                        if ($before !== $this->vidOwnershipSnapshot($vid->refresh())) {
                            $repairSummary['synced_services']++;
                        }

                        return;
                    }

                    if ($vid->status !== VidStatus::Available || $vid->customer_id !== null || $vid->service_id !== null) {
                        $vid->forceFill([
                            'status' => VidStatus::Available->value,
                            'customer_id' => null,
                            'service_id' => null,
                        ])->save();

                        $repairSummary['released_vids']++;
                    }
                });

            Service::query()
                ->active()
                ->whereNotNull('vid_id')
                ->orderBy('id')
                ->get()
                ->each(function (Service $service) use (&$repairSummary): void {
                    $vid = $this->lockServiceVid($service);

                    if ($vid === null) {
                        return;
                    }

                    try {
                        $before = $this->vidOwnershipSnapshot($vid);
                        $this->assignToService($service, $vid);

                        if ($before !== $this->vidOwnershipSnapshot($vid->refresh())) {
                            $repairSummary['synced_services']++;
                        }
                    } catch (ValidationException) {
                        $repairSummary['skipped_conflicts']++;
                    }
                });

            $after = $this->audit();

            return [
                'before' => $before,
                'after' => $after,
                'repair_summary' => $repairSummary,
            ];
        });
    }

    private function ensureVidMatchesService(Service $service, Vid $vid): void
    {
        if ((int) ($service->vid_id ?? 0) !== (int) $vid->id) {
            throw ValidationException::withMessages([
                'vid_id' => 'The service vid_id must match the assigned VID record.',
            ]);
        }

        if ($vid->vid_type !== VidType::CustomerInternet) {
            throw ValidationException::withMessages([
                'vid_id' => 'The selected VID must be a customer internet VID.',
            ]);
        }

        if ((int) $service->router_id !== (int) $vid->router_id) {
            throw ValidationException::withMessages([
                'router_id' => 'The service router must match the selected VID router.',
            ]);
        }

        if ((int) $service->internet_vid !== (int) $vid->vid) {
            throw ValidationException::withMessages([
                'internet_vid' => 'The service internet_vid must match the selected VID.',
            ]);
        }
    }

    private function lockServiceVid(Service $service): ?Vid
    {
        if ($service->vid_id === null) {
            return null;
        }

        return Vid::query()
            ->lockForUpdate()
            ->find($service->vid_id);
    }

    private function assignedService(Vid $vid): ?Service
    {
        if ($vid->service_id === null) {
            return null;
        }

        return Service::withTrashed()->find($vid->service_id);
    }

    private function appendAssignedVidIssues(array &$issues): void
    {
        Vid::query()
            ->where(function ($query): void {
                $query
                    ->where('status', VidStatus::Assigned->value)
                    ->orWhereNotNull('service_id');
            })
            ->orderBy('id')
            ->get()
            ->each(function (Vid $vid) use (&$issues): void {
                $activeServices = $this->activeServicesUsingVid($vid);
                $assignedService = $this->assignedService($vid);

                if (
                    $activeServices->isEmpty()
                    && (
                        $vid->status === VidStatus::Assigned
                        || $vid->service_id !== null
                        || $vid->customer_id !== null
                    )
                ) {
                    $issues[self::ISSUE_ASSIGNED_VID_WITHOUT_SERVICE][] = [
                        'vid_id' => $vid->id,
                        'router_id' => $vid->router_id,
                        'vid' => $vid->vid,
                        'status' => $vid->status?->value,
                        'customer_id' => $vid->customer_id,
                        'service_id' => $vid->service_id,
                    ];

                    return;
                }

                if (
                    $vid->service_id !== null
                    && (
                        $assignedService === null
                        || $assignedService->trashed()
                        || ! $assignedService->usesDedicatedResources()
                    )
                ) {
                    $issues[self::ISSUE_SERVICE_VID_MISMATCH][] = [
                        'vid_id' => $vid->id,
                        'router_id' => $vid->router_id,
                        'vid' => $vid->vid,
                        'service_id' => $vid->service_id,
                        'mismatches' => ['vid_points_to_inactive_or_missing_service'],
                    ];
                }
            });
    }

    private function appendDuplicateIssues(array &$issues, Collection $activeServices): void
    {
        $activeServices
            ->filter(fn (Service $service): bool => $service->vid_id !== null)
            ->groupBy(fn (Service $service): int => (int) $service->vid_id)
            ->filter(fn (Collection $services): bool => $services->count() > 1)
            ->each(function (Collection $services, int $vidId) use (&$issues): void {
                $issues[self::ISSUE_DUPLICATE_ACTIVE_VID][] = [
                    'vid_id' => $vidId,
                    'service_ids' => $services->pluck('id')->values()->all(),
                    'service_codes' => $services->pluck('service_code')->values()->all(),
                ];
            });

        $activeServices
            ->groupBy(fn (Service $service): string => $service->router_id.'|'.$service->internet_vid)
            ->filter(fn (Collection $services): bool => $services->count() > 1)
            ->each(function (Collection $services, string $key) use (&$issues): void {
                [$routerId, $internetVid] = explode('|', $key);

                $issues[self::ISSUE_DUPLICATE_ACTIVE_ROUTER_VID][] = [
                    'router_id' => (int) $routerId,
                    'internet_vid' => (int) $internetVid,
                    'service_ids' => $services->pluck('id')->values()->all(),
                    'service_codes' => $services->pluck('service_code')->values()->all(),
                ];
            });
    }

    private function mismatchesForServiceVid(Service $service, Vid $vid): array
    {
        $mismatches = [];

        if ($vid->vid_type !== VidType::CustomerInternet) {
            $mismatches[] = 'vid_type_not_customer_internet';
        }

        if ($vid->status !== VidStatus::Assigned) {
            $mismatches[] = 'vid_status_not_assigned';
        }

        if ((int) ($vid->service_id ?? 0) !== (int) $service->id) {
            $mismatches[] = 'vid_service_id_mismatch';
        }

        if ((int) ($vid->customer_id ?? 0) !== (int) $service->customer_id) {
            $mismatches[] = 'vid_customer_id_mismatch';
        }

        if ((int) $vid->router_id !== (int) $service->router_id) {
            $mismatches[] = 'router_id_mismatch';
        }

        if ((int) $vid->vid !== (int) $service->internet_vid) {
            $mismatches[] = 'internet_vid_mismatch';
        }

        return $mismatches;
    }

    private function serviceReference(Service $service): array
    {
        return [
            'service_id' => $service->id,
            'service_code' => $service->service_code,
            'customer_id' => $service->customer_id,
            'router_id' => $service->router_id,
            'overall_status' => $service->overall_status?->value,
        ];
    }

    private function vidOwnershipSnapshot(Vid $vid): array
    {
        return [
            'status' => $vid->status?->value,
            'customer_id' => $vid->customer_id,
            'service_id' => $vid->service_id,
        ];
    }

    private function emptyIssueBuckets(): array
    {
        return [
            self::ISSUE_SERVICE_WITHOUT_VID => [],
            self::ISSUE_ASSIGNED_VID_WITHOUT_SERVICE => [],
            self::ISSUE_SERVICE_VID_MISMATCH => [],
            self::ISSUE_DUPLICATE_ACTIVE_VID => [],
            self::ISSUE_DUPLICATE_ACTIVE_ROUTER_VID => [],
        ];
    }

    private function summarizeIssues(array $issues): array
    {
        $summary = [];
        $total = 0;

        foreach ($issues as $type => $typeIssues) {
            $count = count($typeIssues);
            $summary[$type] = $count;
            $total += $count;
        }

        $summary['total_issues'] = $total;

        return $summary;
    }
}
