<?php

namespace App\Services\Customer;

use App\Enums\VidStatus;
use App\Models\Location;
use App\Models\Olt;
use App\Models\Package;
use App\Models\Router;
use App\Models\Technician;
use App\Models\Vid;
use App\Services\Access\RoleRouterScopeService;

class CustomerReferenceService
{
    public function __construct(private readonly RoleRouterScopeService $roleRouterScopeService) {}

    public function get(array $filters = []): array
    {
        $onlyActive = (bool) ($filters['only_active'] ?? true);

        if (($filters['router_id'] ?? null) !== null) {
            $this->roleRouterScopeService->ensureRouterAccessible((int) $filters['router_id']);
        }

        return [
            'locations' => Location::query()
                ->when($onlyActive, fn ($query) => $query->where('is_active', true))
                ->orderBy('location_name')
                ->get(['id', 'location_code', 'location_name', 'is_active'])
                ->map(static fn (Location $location): array => [
                    'id' => $location->id,
                    'location_code' => $location->location_code,
                    'location_name' => $location->location_name,
                    'is_active' => (bool) $location->is_active,
                ])
                ->all(),
            'olts' => Olt::query()
                ->with('location:id,location_code,location_name')
                ->when($onlyActive, fn ($query) => $query->where('is_active', true))
                ->when($filters['location_id'] ?? null, fn ($query, $locationId) => $query->where('location_id', $locationId))
                ->orderBy('olt_name')
                ->get(['id', 'olt_code', 'olt_name', 'location_id', 'location_name', 'is_active', 'router_id', 'network_location_id'])
                ->map(static function (Olt $olt): array {
                    return [
                        'id' => $olt->id,
                        'olt_code' => $olt->olt_code,
                        'olt_name' => $olt->olt_name,
                        'location_id' => $olt->network_location_id ?? $olt->location_id,
                        'network_location_id' => $olt->network_location_id,
                        'location_name' => $olt->location_name,
                        'router_id' => $olt->router_id,
                        'is_active' => (bool) $olt->is_active,
                        'location' => $olt->location === null ? null : [
                            'id' => $olt->location->id,
                            'location_code' => $olt->location->location_code,
                            'location_name' => $olt->location->location_name,
                        ],
                    ];
                })
                ->all(),
            'routers' => tap(
                Router::query()
                    ->when($onlyActive, fn ($query) => $query->where('is_active', true)),
                fn ($query) => $this->roleRouterScopeService->applyRouterScope($query, 'id')
            )
                ->orderBy('router_name')
                ->get(['id', 'router_code', 'router_name', 'router_role', 'is_active'])
                ->map(static fn (Router $router): array => [
                    'id' => $router->id,
                    'router_code' => $router->router_code,
                    'router_name' => $router->router_name,
                    'router_role' => $router->router_role,
                    'is_active' => (bool) $router->is_active,
                ])
                ->all(),
            'packages' => Package::query()
                ->when($onlyActive, fn ($query) => $query->where('is_active', true))
                ->orderBy('package_name')
                ->get(['id', 'package_name', 'monthly_price', 'ip_pool_count', 'rate_limit_mbps', 'is_active'])
                ->map(static fn (Package $package): array => [
                    'id' => $package->id,
                    'package_name' => $package->package_name,
                    'monthly_price' => $package->monthly_price,
                    'ip_pool_count' => $package->ip_pool_count,
                    'rate_limit_mbps' => $package->rate_limit_mbps,
                    'is_active' => (bool) $package->is_active,
                ])
                ->all(),
            'technicians' => Technician::query()
                ->when($onlyActive, fn ($query) => $query->where('is_active', true))
                ->when(
                    $filters['router_id'] ?? null,
                    fn ($query, $routerId) => $query->whereHas('user', function ($q) use ($routerId) {
                        $q->whereHas('accessibleRouters', fn ($rq) => $rq->where('routers.id', $routerId));
                    })
                )
                ->orderBy('full_name')
                ->get(['id', 'technician_code', 'full_name', 'is_active'])
                ->map(static fn (Technician $technician): array => [
                    'id' => $technician->id,
                    'technician_code' => $technician->technician_code,
                    'full_name' => $technician->full_name,
                    'is_active' => (bool) $technician->is_active,
                ])
                ->all(),
            'available_vids' => tap(
                Vid::query()
                    ->with(['router:id,router_code,router_name', 'routerScope:id,router_id,scope_name,monitor_vid'])
                    ->where('status', VidStatus::Available->value)
                    ->when($filters['router_id'] ?? null, fn ($query, $routerId) => $query->where('router_id', $routerId)),
                fn ($query) => $this->roleRouterScopeService->applyRouterScope($query, 'router_id')
            )
                ->orderBy('vid')
                ->limit(200)
                ->get([
                    'id',
                    'router_id',
                    'scope_id',
                    'vid',
                    'subnet_cidr',
                    'gateway_ip',
                    'pool_start_ip',
                    'pool_end_ip',
                    'status',
                ])
                ->map(static function (Vid $vid): array {
                    return [
                        'id' => $vid->id,
                        'router_id' => $vid->router_id,
                        'vid' => $vid->vid,
                        'subnet_cidr' => $vid->subnet_cidr,
                        'gateway_ip' => $vid->gateway_ip,
                        'pool_start_ip' => $vid->pool_start_ip,
                        'pool_end_ip' => $vid->pool_end_ip,
                        'status' => $vid->status?->value,
                        'router' => $vid->router === null ? null : [
                            'id' => $vid->router->id,
                            'router_code' => $vid->router->router_code,
                            'router_name' => $vid->router->router_name,
                        ],
                        'scope' => $vid->routerScope === null ? null : [
                            'id' => $vid->routerScope->id,
                            'scope_name' => $vid->routerScope->scope_name,
                            'monitor_vid' => $vid->routerScope->monitor_vid,
                        ],
                    ];
                })
                ->all(),
        ];
    }
}
