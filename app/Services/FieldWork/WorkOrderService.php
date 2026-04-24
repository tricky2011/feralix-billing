<?php

namespace App\Services\FieldWork;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkOrderService
{
    private const INDEX_RELATIONS = [
        'customer:id,customer_code,full_name',
        'service:id,customer_id,service_code,deleted_at',
        'router:id,router_code,router_name',
        'olt:id,olt_code,olt_name',
        'ont:id,olt_id,ont_sn,ont_name,status',
        'assignedTechnician:id,technician_code,full_name,is_active',
    ];

    public function __construct(private readonly RoleRouterScopeService $roleRouterScopeService) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        if (($filters['router_id'] ?? null) !== null) {
            $this->roleRouterScopeService->ensureRouterAccessible((int) $filters['router_id']);
        }

        $query = WorkOrder::query()
            ->with(self::INDEX_RELATIONS)
            ->search($filters['search'] ?? null)
            ->when(
                $filters['customer_id'] ?? null,
                fn (Builder $builder, $customerId) => $builder->where('customer_id', $customerId)
            )
            ->when(
                $filters['service_id'] ?? null,
                fn (Builder $builder, $serviceId) => $builder->where('service_id', $serviceId)
            )
            ->when(
                $filters['router_id'] ?? null,
                fn (Builder $builder, $routerId) => $builder->where('router_id', $routerId)
            )
            ->when(
                $filters['assigned_technician_id'] ?? null,
                fn (Builder $builder, $technicianId) => $builder->where('assigned_technician_id', $technicianId)
            )
            ->when(
                $filters['wo_type'] ?? null,
                fn (Builder $builder, $woType) => $builder->where('wo_type', $woType)
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $builder, $status) => $builder->where('status', $status)
            );

        $this->roleRouterScopeService->applyRouterScope($query, 'router_id');

        return $query
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $payload): WorkOrder
    {
        $this->roleRouterScopeService->ensureRouterAccessible((int) $payload['router_id']);

        if (($payload['service_id'] ?? null) !== null) {
            $this->roleRouterScopeService->findAccessibleService((int) $payload['service_id']);
        }

        return DB::transaction(function () use ($payload): WorkOrder {
            $workOrder = WorkOrder::query()->create(
                $this->preparePayload($payload)
            );

            return $this->loadWorkOrder($workOrder);
        });
    }

    public function find(WorkOrder $workOrder): WorkOrder
    {
        return $this->loadWorkOrder($workOrder);
    }

    public function update(WorkOrder $workOrder, array $payload): WorkOrder
    {
        $this->roleRouterScopeService->ensureRouterAccessible((int) $payload['router_id']);

        if (($payload['service_id'] ?? null) !== null) {
            $this->roleRouterScopeService->findAccessibleService((int) $payload['service_id']);
        }

        return DB::transaction(function () use ($workOrder, $payload): WorkOrder {
            $workOrder->update(
                $this->preparePayload($payload, $workOrder)
            );

            return $this->loadWorkOrder($workOrder);
        });
    }

    public function delete(WorkOrder $workOrder): void
    {
        $workOrder->delete();
    }

    private function loadWorkOrder(WorkOrder $workOrder): WorkOrder
    {
        $workOrder = $workOrder->refresh();
        $workOrder->loadMissing(self::INDEX_RELATIONS);

        return $workOrder;
    }

    private function preparePayload(array $payload, ?WorkOrder $workOrder = null): array
    {
        $assignedTechnicianId = array_key_exists('assigned_technician_id', $payload)
            ? $payload['assigned_technician_id']
            : $workOrder?->assigned_technician_id;
        $resolvedStatus = $this->resolveStatus(
            $payload['status'] ?? $workOrder?->status?->value,
            $assignedTechnicianId
        );

        $attributes = [
            ...$payload,
            'status' => $resolvedStatus,
        ];

        if ($workOrder === null) {
            $attributes['wo_number'] = $this->generateWorkOrderNumber();
        }

        if ($resolvedStatus === WorkOrderStatus::Completed->value) {
            $attributes['completed_at'] = $payload['completed_at'] ?? $workOrder?->completed_at ?? now();
        } else {
            $attributes['completed_at'] = null;
        }

        return $attributes;
    }

    private function resolveStatus(?string $requestedStatus, ?int $assignedTechnicianId): string
    {
        if ($requestedStatus === null || $requestedStatus === '') {
            return $assignedTechnicianId !== null
                ? WorkOrderStatus::Assigned->value
                : WorkOrderStatus::Open->value;
        }

        if (
            $requestedStatus === WorkOrderStatus::Open->value
            && $assignedTechnicianId !== null
        ) {
            return WorkOrderStatus::Assigned->value;
        }

        if (
            in_array($requestedStatus, [
                WorkOrderStatus::Assigned->value,
                WorkOrderStatus::InProgress->value,
                WorkOrderStatus::Completed->value,
            ], true)
            && $assignedTechnicianId === null
        ) {
            return WorkOrderStatus::Open->value;
        }

        return $requestedStatus;
    }

    private function generateWorkOrderNumber(): string
    {
        do {
            $workOrderNumber = sprintf(
                'WO-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(6))
            );
        } while (WorkOrder::query()->where('wo_number', $workOrderNumber)->exists());

        return $workOrderNumber;
    }
}
