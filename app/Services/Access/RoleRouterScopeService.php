<?php

namespace App\Services\Access;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Router;
use App\Models\RouterScope;
use App\Models\Service;
use App\Models\ServiceIsolation;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vid;
use App\Models\WorkOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RoleRouterScopeService
{
    public function allowsAdminPanel(?Authenticatable $candidate = null): bool
    {
        return $this->allowsRoles($candidate, UserRole::Superadmin, UserRole::Admin);
    }

    public function allowsTechnicianPanel(?Authenticatable $candidate = null): bool
    {
        return $this->allowsRoles($candidate, UserRole::Technician);
    }

    public function allowsRoles(?Authenticatable $candidate, UserRole ...$roles): bool
    {
        $user = $this->resolveUser($candidate);

        if ($user === null) {
            return false;
        }

        return in_array($user->role, $roles, true);
    }

    /**
     * @return list<int>|null
     */
    public function visibleRouterIds(?Authenticatable $candidate = null): ?array
    {
        $user = $this->resolveUser($candidate);

        if ($user === null) {
            return app()->runningInConsole()
                ? null
                : [];
        }

        if ($user->isSuperadmin()) {
            return null;
        }

        $user->loadMissing('accessibleRouters:id');

        return $user->accessibleRouters
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function applyRouterScope(
        Builder $query,
        string $qualifiedColumn = 'router_id',
        ?Authenticatable $candidate = null,
    ): Builder {
        $routerIds = $this->visibleRouterIds($candidate);

        if ($routerIds === null) {
            return $query;
        }

        if ($routerIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($qualifiedColumn, $routerIds);
    }

    public function applyServiceRouterScope(
        Builder $query,
        string $relation = 'service',
        string $qualifiedColumn = 'router_id',
        ?string $nullableForeignKey = null,
        ?Authenticatable $candidate = null,
    ): Builder {
        $routerIds = $this->visibleRouterIds($candidate);

        if ($routerIds === null) {
            return $query;
        }

        if ($routerIds === []) {
            return $nullableForeignKey === null
                ? $query->whereRaw('1 = 0')
                : $query->whereNull($nullableForeignKey);
        }

        if ($nullableForeignKey === null) {
            return $query->whereHas($relation, function (Builder $relationQuery) use ($qualifiedColumn, $routerIds): void {
                $relationQuery->whereIn($qualifiedColumn, $routerIds);
            });
        }

        return $query->where(function (Builder $builder) use ($relation, $qualifiedColumn, $nullableForeignKey, $routerIds): void {
            $builder->whereNull($nullableForeignKey);
            $builder->orWhereHas($relation, function (Builder $relationQuery) use ($qualifiedColumn, $routerIds): void {
                $relationQuery->whereIn($qualifiedColumn, $routerIds);
            });
        });
    }

    public function applyCustomerScope(
        Builder $query,
        string $relation = 'services',
        string $qualifiedColumn = 'router_id',
        ?Authenticatable $candidate = null,
    ): Builder {
        $routerIds = $this->visibleRouterIds($candidate);

        if ($routerIds === null) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($relation, $qualifiedColumn, $routerIds): void {
            $builder->whereDoesntHave($relation);

            if ($routerIds !== []) {
                $builder->orWhereHas($relation, function (Builder $relationQuery) use ($qualifiedColumn, $routerIds): void {
                    $relationQuery->whereIn($qualifiedColumn, $routerIds);
                });
            }
        });
    }

    public function applyOltScope(
        Builder $query,
        string $qualifiedColumn = 'router_id',
        ?Authenticatable $candidate = null,
    ): Builder {
        $routerIds = $this->visibleRouterIds($candidate);

        if ($routerIds === null) {
            return $query;
        }

        if ($routerIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('olt', function (Builder $oltQuery) use ($qualifiedColumn, $routerIds): void {
            $oltQuery->whereIn($qualifiedColumn, $routerIds);
        });
    }

    public function applyNetworkLocationScopeViaOlt(
        Builder $query,
        ?Authenticatable $candidate = null,
    ): Builder {
        $routerIds = $this->visibleRouterIds($candidate);

        if ($routerIds === null) {
            return $query;
        }

        if ($routerIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('olts', function (Builder $oltQuery) use ($routerIds): void {
            $oltQuery->whereIn('router_id', $routerIds);
        });
    }

    public function ensureRouterAccessible(
        ?int $routerId,
        string $key = 'router_id',
        ?Authenticatable $candidate = null,
    ): void {
        if ($routerId === null) {
            return;
        }

        $visibleRouterIds = $this->visibleRouterIds($candidate);

        if ($visibleRouterIds === null || in_array($routerId, $visibleRouterIds, true)) {
            return;
        }

        throw ValidationException::withMessages([
            $key => 'The selected router is outside your assigned router scope.',
        ]);
    }

    public function ensureModelAccessible(Model $model, ?Authenticatable $candidate = null): void
    {
        $resourceRouterIds = $this->modelRouterIds($model);

        if ($resourceRouterIds === null) {
            return;
        }

        $visibleRouterIds = $this->visibleRouterIds($candidate);

        if ($visibleRouterIds === null || array_intersect($resourceRouterIds, $visibleRouterIds) !== []) {
            return;
        }

        throw new AuthorizationException('The selected resource is outside your assigned router scope.');
    }

    public function ensureModelAccessibleForInput(
        Model $model,
        string $key,
        ?Authenticatable $candidate = null,
    ): void {
        $resourceRouterIds = $this->modelRouterIds($model);

        if ($resourceRouterIds === null) {
            return;
        }

        $visibleRouterIds = $this->visibleRouterIds($candidate);

        if ($visibleRouterIds === null || array_intersect($resourceRouterIds, $visibleRouterIds) !== []) {
            return;
        }

        throw ValidationException::withMessages([
            $key => 'The selected resource is outside your assigned router scope.',
        ]);
    }

    public function ensureRouteBindingsAccessible(iterable $bindings, ?Authenticatable $candidate = null): void
    {
        foreach ($bindings as $binding) {
            if ($binding instanceof Model) {
                $this->ensureModelAccessible($binding, $candidate);
            }
        }
    }

    public function findAccessibleCustomer(
        int $customerId,
        string $key = 'customer_id',
        ?Authenticatable $candidate = null,
    ): Customer {
        $customer = Customer::query()->findOrFail($customerId);
        $this->ensureModelAccessibleForInput($customer, $key, $candidate);

        return $customer;
    }

    public function findAccessibleInvoice(
        int $invoiceId,
        string $key = 'invoice_id',
        ?Authenticatable $candidate = null,
    ): Invoice {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->ensureModelAccessibleForInput($invoice, $key, $candidate);

        return $invoice;
    }

    public function findAccessibleRouterScope(
        int $scopeId,
        string $key = 'scope_id',
        ?Authenticatable $candidate = null,
    ): RouterScope {
        $scope = RouterScope::query()->findOrFail($scopeId);
        $this->ensureModelAccessibleForInput($scope, $key, $candidate);

        return $scope;
    }

    public function findAccessibleService(
        int $serviceId,
        string $key = 'service_id',
        ?Authenticatable $candidate = null,
    ): Service {
        $service = Service::query()->withTrashed()->findOrFail($serviceId);
        $this->ensureModelAccessibleForInput($service, $key, $candidate);

        return $service;
    }

    public function findAccessibleVid(
        int $vidId,
        string $key = 'vid_id',
        ?Authenticatable $candidate = null,
    ): Vid {
        $vid = Vid::query()->findOrFail($vidId);
        $this->ensureModelAccessibleForInput($vid, $key, $candidate);

        return $vid;
    }

    private function resolveUser(?Authenticatable $candidate = null): ?User
    {
        $candidate ??= Auth::user();

        return $candidate instanceof User ? $candidate : null;
    }

    /**
     * @return list<int>|null
     */
    private function modelRouterIds(Model $model): ?array
    {
        return match (true) {
            $model instanceof Router => $this->normalizeRouterIds([$model->getKey()]),
            $model instanceof RouterScope => $this->normalizeRouterIds([$model->router_id]),
            $model instanceof Vid => $this->normalizeRouterIds([$model->router_id]),
            $model instanceof Service => $this->normalizeRouterIds([$model->router_id]),
            $model instanceof ServiceIsolation => $this->normalizeRouterIds([
                $model->router_id,
                ...($this->serviceRouterIds($model->service_id) ?? []),
                ...$this->invoiceRouterIds($model->invoice_id),
            ]),
            $model instanceof WorkOrder => $this->normalizeRouterIds([
                $model->router_id,
                ...($this->serviceRouterIds($model->service_id) ?? []),
            ]),
            $model instanceof Invoice => $this->serviceRouterIds($model->service_id),
            $model instanceof Payment => $this->normalizeRouterIds([
                ...($this->serviceRouterIds($model->service_id) ?? []),
                ...$this->invoiceRouterIds($model->invoice_id),
            ]),
            $model instanceof Ticket => $this->normalizeRouterIds([
                ...($this->serviceRouterIds($model->service_id) ?? []),
                ...($this->customerRouterIds($model->customer_id) ?? []),
            ]),
            $model instanceof Customer => $this->customerRouterIds($model->getKey()),
            default => null,
        };
    }

    /**
     * @return list<int>|null
     */
    private function customerRouterIds(?int $customerId): ?array
    {
        if ($customerId === null) {
            return null;
        }

        return $this->normalizeRouterIds(
            Service::query()
                ->withTrashed()
                ->where('customer_id', $customerId)
                ->pluck('router_id')
                ->all()
        );
    }

    /**
     * @return list<int>
     */
    private function invoiceRouterIds(?int $invoiceId): array
    {
        if ($invoiceId === null) {
            return [];
        }

        $invoice = Invoice::query()->select(['id', 'service_id'])->find($invoiceId);

        return $invoice === null
            ? []
            : ($this->serviceRouterIds($invoice->service_id) ?? []);
    }

    /**
     * @return list<int>|null
     */
    private function serviceRouterIds(?int $serviceId): ?array
    {
        if ($serviceId === null) {
            return null;
        }

        return $this->normalizeRouterIds(
            Service::query()
                ->withTrashed()
                ->whereKey($serviceId)
                ->pluck('router_id')
                ->all()
        );
    }

    /**
     * @param  list<int|null>|list<mixed>  $routerIds
     * @return list<int>|null
     */
    private function normalizeRouterIds(array $routerIds): ?array
    {
        $normalized = collect($routerIds)
            ->map(static fn (mixed $routerId): int => (int) $routerId)
            ->filter(static fn (int $routerId): bool => $routerId > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $normalized === [] ? null : $normalized;
    }
}
