<?php

namespace App\Services\Dashboard;

use App\Data\Dashboard\DashboardScope;
use App\Enums\UserRole;
use App\Models\Router;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class DashboardAccessService
{
    public function resolveAdminScope(User $user, ?int $requestedRouterId = null): DashboardScope
    {
        if ($user->isTechnician()) {
            throw new AuthorizationException('Technician users must use the technician dashboard.');
        }

        $user->loadMissing([
            'dashboardActiveRouter:id,router_code,router_name,router_role,is_active',
            'accessibleRouters:id,router_code,router_name,router_role,is_active',
        ]);

        if ($user->isSuperadmin()) {
            $selectedRouterId = $requestedRouterId ?? $user->dashboard_active_router_id;

            if ($selectedRouterId !== null && ! Router::query()->whereKey($selectedRouterId)->exists()) {
                throw ValidationException::withMessages([
                    'router_id' => 'The selected router is invalid.',
                ]);
            }

            return new DashboardScope(
                role: UserRole::Superadmin,
                userId: $user->id,
                technicianId: $user->technician_id,
                routerIds: $selectedRouterId !== null ? [$selectedRouterId] : null,
                activeRouterId: $selectedRouterId,
                canSwitchRouter: true,
            );
        }

        $routerIds = $user->accessibleRouters
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return new DashboardScope(
            role: UserRole::Admin,
            userId: $user->id,
            technicianId: $user->technician_id,
            routerIds: $routerIds,
            activeRouterId: count($routerIds) === 1 ? $routerIds[0] : null,
            canSwitchRouter: false,
        );
    }

    public function switchActiveRouter(User $user, ?int $routerId): DashboardScope
    {
        if (! $user->isSuperadmin()) {
            throw new AuthorizationException('Only superadmin can switch the active dashboard router.');
        }

        if ($routerId !== null && ! Router::query()->whereKey($routerId)->exists()) {
            throw ValidationException::withMessages([
                'router_id' => 'The selected router is invalid.',
            ]);
        }

        $user->forceFill([
            'dashboard_active_router_id' => $routerId,
        ])->save();

        return $this->resolveAdminScope($user->refresh(), $routerId);
    }

    public function buildScopePayload(User $user, DashboardScope $scope): array
    {
        $availableRouters = $this->availableRouters($user, $scope);
        $visibleRouterIds = $scope->usesAllRouters()
            ? collect($availableRouters)->pluck('id')->filter()->values()->all()
            : ($scope->visibleRouterIds() ?? []);

        return [
            'role' => $scope->role->value,
            'router_switcher' => [
                'enabled' => $scope->canSwitchRouter,
                'is_all_routers' => $scope->usesAllRouters(),
                'active_router_id' => $scope->activeRouterId,
                'active_router' => collect($availableRouters)
                    ->first(fn (array $router): bool => $router['id'] === $scope->activeRouterId),
                'available_routers' => $availableRouters,
                'visible_router_ids' => $visibleRouterIds,
            ],
        ];
    }

    public function technicianRedirectPayload(): array
    {
        return [
            'dashboard_type' => 'redirect',
            'target_dashboard' => 'technician',
            'redirect_to' => '/api/v1/technician/dashboard',
        ];
    }

    /**
     * @return list<array{id:int|null,router_code:string,router_name:string,router_role:?string,is_active:bool}>
     */
    private function availableRouters(User $user, DashboardScope $scope): array
    {
        $routers = $scope->role === UserRole::Superadmin
            ? Router::query()
                ->orderBy('router_name')
                ->orderBy('router_code')
                ->get(['id', 'router_code', 'router_name', 'router_role', 'is_active'])
            : $user->accessibleRouters;

        $items = $routers
            ->map(fn (Router $router): array => $this->routerOption($router))
            ->values()
            ->all();

        if ($scope->canSwitchRouter) {
            array_unshift($items, [
                'id' => null,
                'router_code' => 'ALL',
                'router_name' => 'Semua Router',
                'router_role' => null,
                'is_active' => true,
            ]);
        }

        return $items;
    }

    /**
     * @return array{id:int,router_code:string,router_name:string,router_role:string,is_active:bool}
     */
    private function routerOption(Router $router): array
    {
        return [
            'id' => $router->id,
            'router_code' => $router->router_code,
            'router_name' => $router->router_name,
            'router_role' => $router->router_role,
            'is_active' => (bool) $router->is_active,
        ];
    }
}
