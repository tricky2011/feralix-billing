<?php

namespace App\Services\MasterData;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\WorkOrder;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(private readonly RoleRouterScopeService $roleRouterScopeService) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $sortBy = $filters['sort_by'] ?? 'full_name';
        $sortDirection = $filters['sort_direction'] ?? 'asc';

        if (($filters['router_id'] ?? null) !== null) {
            $this->roleRouterScopeService->ensureRouterAccessible((int) $filters['router_id']);
        }

        $query = Customer::query()
            ->with($this->indexRelations())
            ->withCount('services')
            ->withCount([
                'services as active_services_count' => fn (Builder $query) => $query->active(),
            ])
            ->search($filters['search'] ?? null)
            ->when(
                $filters['customer_type'] ?? null,
                fn (Builder $query, $customerType) => $query->where('customer_type', $customerType)
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, $status) => $query->where('status', $status)
            )
            ->when(
                $filters['location_id'] ?? null,
                fn (Builder $query, $locationId) => $query->where('location_id', $locationId)
            )
            ->when(
                $filters['assigned_technician_id'] ?? null,
                fn (Builder $query, $technicianId) => $query->where('assigned_technician_id', $technicianId)
            )
            ->when(
                $filters['router_id'] ?? null,
                fn (Builder $query, $routerId) => $query->whereHas('services', fn (Builder $serviceQuery) => $serviceQuery->where('router_id', $routerId))
            )
            ->when(
                $filters['olt_id'] ?? null,
                function (Builder $query, $oltId): void {
                    $query->where(function (Builder $builder) use ($oltId): void {
                        $builder
                            ->where('preferred_olt_id', $oltId)
                            ->orWhereHas('services', fn (Builder $serviceQuery) => $serviceQuery->where('olt_id', $oltId));
                    });
                }
            )
            ->when(
                array_key_exists('has_active_service', $filters) && $filters['has_active_service'] !== null,
                function (Builder $query) use ($filters): void {
                    $filters['has_active_service'] === true
                        ? $query->whereHas('services', fn (Builder $serviceQuery) => $serviceQuery->active())
                        : $query->whereDoesntHave('services', fn (Builder $serviceQuery) => $serviceQuery->active());
                }
            );

        $this->roleRouterScopeService->applyCustomerScope($query);

        return $query
            ->orderBy($sortBy, $sortDirection)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $payload): Customer
    {
        $customer = Customer::query()->create($this->customerPayload($payload));

        return $this->loadCustomer($customer);
    }

    public function find(Customer $customer): Customer
    {
        return $this->loadCustomer($customer, true);
    }

    public function update(Customer $customer, array $payload): Customer
    {
        $customer->update($this->customerPayload($payload));

        return $this->loadCustomer($customer, true);
    }

    public function delete(Customer $customer): void
    {
        $blockers = $this->deletionBlockers($customer);

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'customer' => [
                    sprintf(
                        'Customer %s cannot be deleted because it still has related records: %s',
                        $customer->customer_code,
                        implode(', ', $blockers),
                    ),
                ],
            ]);
        }

        $customer->delete();
    }

    private function loadCustomer(Customer $customer, bool $withServiceHistory = false): Customer
    {
        $customer = $customer->refresh();

        $customer->load($this->indexRelations());
        $customer->loadCount([
            'services',
            'services as active_services_count' => fn (Builder $query) => $query->active(),
        ]);

        if ($withServiceHistory) {
            $customer->load([
                'services' => fn ($query) => $query
                    ->withTrashed()
                    ->with($this->serviceRelations())
                    ->orderByDesc('created_at')
                    ->orderByDesc('id'),
            ]);
        }

        return $customer;
    }

    /**
     * @return list<string>
     */
    public function deletionBlockers(Customer $customer): array
    {
        $blockers = [];

        if (Service::query()->withTrashed()->where('customer_id', $customer->id)->exists()) {
            $blockers[] = 'services';
        }

        if (Invoice::query()->where('customer_id', $customer->id)->exists()) {
            $blockers[] = 'invoices';
        }

        if (Payment::query()->where('customer_id', $customer->id)->exists()) {
            $blockers[] = 'payments';
        }

        if (Ticket::query()->where('customer_id', $customer->id)->exists()) {
            $blockers[] = 'tickets';
        }

        if (WorkOrder::query()->where('customer_id', $customer->id)->exists()) {
            $blockers[] = 'work_orders';
        }

        return $blockers;
    }

    private function customerPayload(array $payload): array
    {
        return Arr::only($payload, [
            'customer_code',
            'full_name',
            'phone',
            'address',
            'location_id',
            'preferred_olt_id',
            'assigned_technician_id',
            'latitude',
            'longitude',
            'customer_type',
            'status',
            'ip_count',
            'monthly_price',
            'billing_day',
            'pppoe_username',
            'pppoe_password',
        ]);
    }

    private function indexRelations(): array
    {
        return [
            'location:id,location_code,location_name',
            'preferredOlt:id,olt_code,olt_name,location_id,location_name',
            'assignedTechnician:id,technician_code,full_name,is_active',
            'latestActiveService' => function ($query): void {
                $service = new Service();

                $query->select(array_map(
                    fn (string $column): string => $service->qualifyColumn($column),
                    [
                        'id',
                        'customer_id',
                        'package_id',
                        'router_id',
                        'olt_id',
                        'vid_id',
                        'service_code',
                        'internet_vid',
                        'subnet_cidr',
                        'gateway_ip',
                        'dhcp_pool_start',
                        'dhcp_pool_end',
                        'billing_status',
                        'network_status',
                        'overall_status',
                        'activation_date',
                    ],
                ));
            },
            'latestActiveService.package:id,package_name,monthly_price',
            'latestActiveService.router:id,router_code,router_name',
            'latestActiveService.olt:id,olt_code,olt_name',
            'latestActiveService.vid:id,vid,status',
        ];
    }

    private function serviceRelations(): array
    {
        return [
            'package:id,package_name,monthly_price',
            'router:id,router_code,router_name',
            'olt:id,olt_code,olt_name',
            'ont:id,olt_id,ont_sn,ont_name,status',
            'vid:id,router_id,vid,status,subnet_cidr,gateway_ip',
        ];
    }
}
