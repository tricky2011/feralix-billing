<?php

namespace App\Services\Provisioning;

use App\Models\ServiceIsolation;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ServiceIsolationHistoryService
{
    public function __construct(private readonly RoleRouterScopeService $roleRouterScopeService) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        if (($filters['router_id'] ?? null) !== null) {
            $this->roleRouterScopeService->ensureRouterAccessible((int) $filters['router_id']);
        }

        if (($filters['service_id'] ?? null) !== null) {
            $this->roleRouterScopeService->findAccessibleService((int) $filters['service_id']);
        }

        if (($filters['customer_id'] ?? null) !== null) {
            $this->roleRouterScopeService->findAccessibleCustomer((int) $filters['customer_id']);
        }

        if (($filters['invoice_id'] ?? null) !== null) {
            $this->roleRouterScopeService->findAccessibleInvoice((int) $filters['invoice_id']);
        }

        $query = ServiceIsolation::query()
            ->with([
                'service.customer:id,customer_code,full_name',
                'service.routerOperationStatus:id,service_id,router_id,access_mode,isolation_target_type,pppoe_username,static_ip_address,queue_name,queue_found,address_list_name,address_list_found,last_synced_at,isolation_detected_via,isolation_verified_at',
                'router:id,router_code,router_name',
                'invoice:id,service_id,invoice_number,due_date,payment_status',
                'creator:id,name,email',
            ])
            ->when(
                $filters['search'] ?? null,
                function (Builder $builder, string $search): void {
                    $builder->where(function (Builder $nested) use ($search): void {
                        $nested
                            ->where('target_identifier', 'like', "%{$search}%")
                            ->orWhere('target_subnet', 'like', "%{$search}%")
                            ->orWhere('address_list_name', 'like', "%{$search}%")
                            ->orWhereHas('service', function (Builder $serviceQuery) use ($search): void {
                                $serviceQuery
                                    ->where('service_code', 'like', "%{$search}%")
                                    ->orWhereHas('customer', function (Builder $customerQuery) use ($search): void {
                                        $customerQuery
                                            ->where('customer_code', 'like', "%{$search}%")
                                            ->orWhere('full_name', 'like', "%{$search}%");
                                    });
                            })
                            ->orWhereHas('invoice', function (Builder $invoiceQuery) use ($search): void {
                                $invoiceQuery->where('invoice_number', 'like', "%{$search}%");
                            });
                    });
                }
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $builder, $status) => $builder->where('status', $status)
            )
            ->when(
                $filters['router_id'] ?? null,
                fn (Builder $builder, $routerId) => $builder->where('router_id', $routerId)
            )
            ->when(
                $filters['service_id'] ?? null,
                fn (Builder $builder, $serviceId) => $builder->where('service_id', $serviceId)
            )
            ->when(
                $filters['invoice_id'] ?? null,
                fn (Builder $builder, $invoiceId) => $builder->where('invoice_id', $invoiceId)
            )
            ->when(
                $filters['customer_id'] ?? null,
                fn (Builder $builder, $customerId) => $builder->whereHas(
                    'service',
                    fn (Builder $serviceQuery) => $serviceQuery->where('customer_id', $customerId)
                )
            )
            ->when(
                $filters['date_from'] ?? null,
                fn (Builder $builder, $dateFrom) => $builder->whereDate('created_at', '>=', $dateFrom)
            )
            ->when(
                $filters['date_to'] ?? null,
                fn (Builder $builder, $dateTo) => $builder->whereDate('created_at', '<=', $dateTo)
            );

        $this->roleRouterScopeService->applyRouterScope($query, 'router_id');

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
