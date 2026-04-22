<?php

namespace App\Services\Provisioning;

use App\Enums\ServiceIsolationStatus;
use App\Enums\ServiceIsolationTargetType;
use App\Models\Service;
use Illuminate\Support\Collection;

class ServiceIsolationSuggestionService
{
    public function __construct(private readonly ServiceIsolationTargetResolver $targetResolver) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function suggest(array $filters): Collection
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 20), 100));
        $query = Service::query()
            ->with([
                'customer:id,full_name',
                'router:id,router_code,router_name',
                'routerOperationStatus',
                'serviceIsolations' => fn ($builder) => $builder
                    ->open()
                    ->latest('id')
                    ->limit(1),
            ])
            ->when(
                $filters['service_id'] ?? null,
                fn ($builder, $serviceId) => $builder->whereKey($serviceId)
            )
            ->when(
                $filters['customer_id'] ?? null,
                fn ($builder, $customerId) => $builder->where('customer_id', $customerId)
            )
            ->when(
                $filters['router_id'] ?? null,
                fn ($builder, $routerId) => $builder->where('router_id', $routerId)
            )
            ->when(
                $filters['search'] ?? null,
                function ($builder, string $search): void {
                    $builder->where(function ($nested) use ($search): void {
                        $nested
                            ->search($search)
                            ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('full_name', 'like', "%{$search}%"));
                    });
                }
            )
            ->orderBy('service_code')
            ->limit($limit);

        return $query->get()->map(function (Service $service): array {
            $target = $this->targetResolver->resolve($service);
            $openIsolation = $service->serviceIsolations->first();
            $operationStatus = $service->routerOperationStatus;

            return [
                'service_id' => $service->id,
                'customer_id' => $service->customer_id,
                'router_id' => $service->router_id,
                'service_code' => $service->service_code,
                'customer_name' => $service->customer?->full_name,
                'router_code' => $service->router?->router_code,
                'access_mode' => $service->resolvedAccessMode()->value,
                'isolation_method' => $service->isolation_method?->value,
                'target_type' => $target['target_type'],
                'target_identifier' => $target['target_identifier'],
                'target_subnet' => $target['target_subnet'],
                'address_list_name' => $target['address_list_name'],
                'target_payload' => $target['target_payload'],
                'open_isolation' => $openIsolation === null ? null : [
                    'id' => $openIsolation->id,
                    'status' => $openIsolation->status?->value,
                    'isolation_type' => $openIsolation->isolation_type?->value,
                    'target_type' => $openIsolation->target_type?->value,
                ],
                'router_operation_status' => $operationStatus === null ? null : [
                    'id' => $operationStatus->id,
                    'access_mode' => $operationStatus->access_mode?->value,
                    'target_type' => $operationStatus->isolation_target_type?->value,
                    'ppp_secret_found' => $operationStatus->ppp_secret_found,
                    'ppp_active_found' => $operationStatus->ppp_active_found,
                    'arp_found' => $operationStatus->arp_found,
                    'queue_found' => $operationStatus->queue_found,
                    'address_list_found' => $operationStatus->address_list_found,
                    'isolation_detected_via' => $operationStatus->isolation_detected_via,
                    'last_synced_at' => $operationStatus->last_synced_at?->toISOString(),
                ],
            ];
        })->filter(function (array $item) use ($filters): bool {
            if (! isset($filters['target_type']) || $filters['target_type'] === null || $filters['target_type'] === '') {
                return true;
            }

            return $item['target_type'] === ServiceIsolationTargetType::from((string) $filters['target_type'])->value;
        })->values();
    }
}
