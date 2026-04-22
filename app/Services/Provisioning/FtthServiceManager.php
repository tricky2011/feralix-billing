<?php

namespace App\Services\Provisioning;

use App\Enums\ServiceAccessMode;
use App\Enums\ServiceIsolationMethod;
use App\Enums\VidStatus;
use App\Models\Package;
use App\Models\Service;
use App\Models\Vid;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FtthServiceManager
{
    public function __construct(private readonly ServiceOverallStateManager $overallStateManager) {}

    private const RELATIONS = [
        'customer:id,customer_code,full_name',
        'package:id,package_name,monthly_price,ip_pool_count,rate_limit_mbps',
        'router:id,router_code,router_name',
        'olt:id,olt_code,olt_name',
        'ont:id,olt_id,ont_sn,ont_name,status',
        'vid:id,router_id,vid,status,subnet_cidr,gateway_ip,customer_id,service_id',
        'routerOperationStatus:id,service_id,router_id,access_mode,isolation_target_type,pppoe_username,static_ip_address,queue_name,queue_found,address_list_name,address_list_found,last_synced_at,isolation_detected_via',
    ];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $query = Service::query()
            ->with(self::RELATIONS)
            ->search($filters['search'] ?? null)
            ->when(
                $filters['customer_id'] ?? null,
                fn ($builder, $customerId) => $builder->where('customer_id', $customerId)
            )
            ->when(
                $filters['package_id'] ?? null,
                fn ($builder, $packageId) => $builder->where('package_id', $packageId)
            )
            ->when(
                $filters['router_id'] ?? null,
                fn ($builder, $routerId) => $builder->where('router_id', $routerId)
            )
            ->when(
                $filters['billing_status'] ?? null,
                fn ($builder, $billingStatus) => $builder->where('billing_status', $billingStatus)
            )
            ->when(
                $filters['network_status'] ?? null,
                fn ($builder, $networkStatus) => $builder->where('network_status', $networkStatus)
            )
            ->when(
                $filters['overall_status'] ?? null,
                fn ($builder, $overallStatus) => $builder->where('overall_status', $overallStatus)
            );

        if (($filters['only_trashed'] ?? false) === true) {
            $query->onlyTrashed();
        } elseif (($filters['with_trashed'] ?? false) === true) {
            $query->withTrashed();
        }

        return $query
            ->orderByDesc('created_at')
            ->orderBy('service_code')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $payload): Service
    {
        return DB::transaction(function () use ($payload): Service {
            $package = $this->resolvePackage($payload['package_id']);
            $vid = $this->lockVid($payload['vid_id'] ?? null);
            $payload = $this->normalizePayloadForPersistence($payload, $package, $vid);

            $service = Service::query()->create($payload);
            $effectiveOverallStatus = $this->overallStateManager->syncDesiredStatus(
                $service,
                $service->overall_status,
            );

            if ($effectiveOverallStatus !== $service->overall_status) {
                $service->update([
                    'overall_status' => $effectiveOverallStatus->value,
                ]);
            }

            $this->syncVidAssignment($service, $vid);

            return $service->refresh()->load(self::RELATIONS);
        });
    }

    public function update(Service $service, array $payload): Service
    {
        return DB::transaction(function () use ($service, $payload): Service {
            $lockedService = Service::query()
                ->lockForUpdate()
                ->findOrFail($service->id);

            $package = $this->resolvePackage($payload['package_id']);
            $lockedVids = $this->lockRelevantVids($lockedService->vid_id, $payload['vid_id'] ?? null);
            $currentVid = $lockedService->vid_id !== null ? $lockedVids->get($lockedService->vid_id) : null;
            $nextVid = ($payload['vid_id'] ?? null) !== null ? $lockedVids->get((int) $payload['vid_id']) : null;

            if (($payload['vid_id'] ?? null) !== null && $nextVid === null) {
                throw ValidationException::withMessages([
                    'vid_id' => 'The selected VID is invalid.',
                ]);
            }

            $payload = $this->normalizePayloadForPersistence($payload, $package, $nextVid);

            $lockedService->update($payload);
            $effectiveOverallStatus = $this->overallStateManager->syncDesiredStatus(
                $lockedService,
                $lockedService->overall_status,
            );

            if ($effectiveOverallStatus !== $lockedService->overall_status) {
                $lockedService->update([
                    'overall_status' => $effectiveOverallStatus->value,
                ]);
            }

            $this->releaseVidAssignment($lockedService, $currentVid);
            $this->syncVidAssignment($lockedService->refresh(), $nextVid);

            return $lockedService->refresh()->load(self::RELATIONS);
        });
    }

    public function delete(Service $service): void
    {
        DB::transaction(function () use ($service): void {
            $lockedService = Service::query()
                ->lockForUpdate()
                ->findOrFail($service->id);

            $vid = $lockedService->vid_id !== null
                ? Vid::query()->lockForUpdate()->find($lockedService->vid_id)
                : null;

            $this->releaseVidAssignment($lockedService, $vid);

            $lockedService->delete();
        });
    }

    private function normalizePayloadForPersistence(array $payload, Package $package, ?Vid $vid): array
    {
        $payload['ip_pool_count'] = $payload['ip_pool_count'] ?? $package->ip_pool_count ?? 5;
        $payload['rate_limit_mbps'] = $payload['rate_limit_mbps'] ?? $package->rate_limit_mbps ?? 10;
        $payload['access_mode'] = $payload['access_mode'] ?? ServiceAccessMode::Vlan->value;
        $payload['isolation_method'] = $payload['isolation_method'] ?? ServiceIsolationMethod::AddressList->value;
        $payload['address_list_name'] = ($payload['isolation_method'] ?? null) === ServiceIsolationMethod::AddressList->value
            ? ($payload['address_list_name'] ?? null)
            : null;

        if ($vid !== null) {
            $payload['vid_id'] = $vid->id;
            $payload['router_id'] = $vid->router_id;
            $payload['internet_vid'] = $vid->vid;
        }

        return $payload;
    }

    private function resolvePackage(int $packageId): Package
    {
        return Package::query()
            ->select(['id', 'package_name', 'monthly_price', 'ip_pool_count', 'rate_limit_mbps'])
            ->findOrFail($packageId);
    }

    private function lockVid(?int $vidId): ?Vid
    {
        if ($vidId === null) {
            return null;
        }

        $vid = Vid::query()
            ->lockForUpdate()
            ->find($vidId);

        if ($vid === null) {
            throw ValidationException::withMessages([
                'vid_id' => 'The selected VID is invalid.',
            ]);
        }

        return $vid;
    }

    private function lockRelevantVids(?int $currentVidId, ?int $nextVidId)
    {
        $vidIds = array_values(array_unique(array_filter([
            $currentVidId,
            $nextVidId,
        ])));

        return Vid::query()
            ->whereIn('id', $vidIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    private function syncVidAssignment(Service $service, ?Vid $vid): void
    {
        if ($vid === null) {
            return;
        }

        if (! $service->usesDedicatedResources()) {
            $this->releaseVidAssignment($service, $vid);

            return;
        }

        if ($vid->service_id !== null && (int) $vid->service_id !== (int) $service->id) {
            throw ValidationException::withMessages([
                'vid_id' => 'The selected VID is already assigned to another service.',
            ]);
        }

        $vid->update([
            'status' => VidStatus::Assigned->value,
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
        ]);
    }

    private function releaseVidAssignment(Service $service, ?Vid $vid): void
    {
        if ($vid === null || (int) ($vid->service_id ?? 0) !== (int) $service->id) {
            return;
        }

        $vid->update([
            'status' => VidStatus::Available->value,
            'customer_id' => null,
            'service_id' => null,
        ]);
    }
}
