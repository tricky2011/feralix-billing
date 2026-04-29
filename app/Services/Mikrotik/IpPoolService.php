<?php

namespace App\Services\Mikrotik;

use App\Contracts\Mikrotik\MikrotikIpPoolProvider;
use App\Data\Mikrotik\MikrotikIpPoolRecord;
use App\Models\Router;
use App\Models\Vid;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Support\Collection;

class IpPoolService
{
    public function __construct(
        private readonly MikrotikIpPoolProviderResolver $providerResolver,
        private readonly RoleRouterScopeService $roleRouterScopeService,
    ) {}

    public function fetchFromRouter(Router $router, ?string $providerName = null): array
    {
        $this->roleRouterScopeService->ensureRouterAccessible($router->id);

        $provider = $this->providerResolver->resolve($providerName);

        return $provider->fetchIpPools($router);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPoolStatusSummary(Router $router, ?string $providerName = null): array
    {
        $pools = $this->fetchFromRouter($router, $providerName);

        $totalPools = count($pools);
        $totalIps = array_reduce($pools, static fn (int $carry, MikrotikIpPoolRecord $pool): int => $carry + $pool->totalIps, 0);
        $totalUsedIps = array_reduce($pools, static fn (int $carry, MikrotikIpPoolRecord $pool): int => $carry + $pool->usedIps, 0);
        $totalFreeIps = array_reduce($pools, static fn (int $carry, MikrotikIpPoolRecord $pool): int => $carry + $pool->freeIps(), 0);

        $poolsWithVlan = array_filter($pools, static fn (MikrotikIpPoolRecord $pool): bool => $pool->vlanId !== null);
        $poolsWithoutVlan = array_filter($pools, static fn (MikrotikIpPoolRecord $pool): bool => $pool->vlanId === null);

        $fullPools = array_filter($pools, static fn (MikrotikIpPoolRecord $pool): bool => $pool->freeIps() === 0);
        $availablePools = array_filter($pools, static fn (MikrotikIpPoolRecord $pool): bool => $pool->freeIps() > 0);

        return [
            'router_id' => $router->id,
            'router_code' => $router->router_code,
            'router_name' => $router->router_name,
            'provider' => $this->providerResolver->resolve($providerName)->name(),
            'total_pools' => $totalPools,
            'total_ips' => $totalIps,
            'total_used_ips' => $totalUsedIps,
            'total_free_ips' => $totalFreeIps,
            'overall_usage_percentage' => $totalIps > 0 ? round(($totalUsedIps / $totalIps) * 100, 2) : 0.0,
            'pools_with_vlan' => count($poolsWithVlan),
            'pools_without_vlan' => count($poolsWithoutVlan),
            'full_pools' => count($fullPools),
            'available_pools' => count($availablePools),
        ];
    }

    /**
     * @return list<MikrotikIpPoolRecord>
     */
    public function getAvailablePools(Router $router, int $minFreeIps = 1, ?string $providerName = null): array
    {
        $pools = $this->fetchFromRouter($router, $providerName);

        return array_values(array_filter(
            $pools,
            static fn (MikrotikIpPoolRecord $pool): bool => $pool->freeIps() >= $minFreeIps,
        ));
    }

    /**
     * @return list<MikrotikIpPoolRecord>
     */
    public function getPoolsByVlan(Router $router, int $vlanId, ?string $providerName = null): array
    {
        $pools = $this->fetchFromRouter($router, $providerName);

        return array_values(array_filter(
            $pools,
            static fn (MikrotikIpPoolRecord $pool): bool => $pool->vlanId === $vlanId,
        ));
    }

    /**
     * Find the best available pool for a new customer based on VID assignment.
     * This matches pools with the corresponding VID to find available network resources.
     *
     * @return list<MikrotikIpPoolRecord>
     */
    public function findPoolsForVid(Router $router, int $vlanId, int $minFreeIps = 1, ?string $providerName = null): array
    {
        $pools = $this->fetchFromRouter($router, $providerName);

        $matchingPools = array_values(array_filter(
            $pools,
            static fn (MikrotikIpPoolRecord $pool): bool =>
                $pool->vlanId === $vlanId && $pool->freeIps() >= $minFreeIps,
        ));

        if ($matchingPools !== []) {
            return $matchingPools;
        }

        return $this->getAvailablePools($router, $minFreeIps, $providerName);
    }

    /**
     * Get pool utilization data by VID for auto-assignment matching.
     *
     * @return array<int, array{vlan_id:int, pool_name:string, free_ips:int, total_ips:int, usage_percentage:float}>
     */
    public function getPoolUtilizationByVid(Router $router, ?string $providerName = null): array
    {
        $pools = $this->fetchFromRouter($router, $providerName);

        $utilization = [];

        foreach ($pools as $pool) {
            if ($pool->vlanId === null) {
                continue;
            }

            if (! isset($utilization[$pool->vlanId])) {
                $utilization[$pool->vlanId] = [
                    'vlan_id' => $pool->vlanId,
                    'pool_name' => $pool->name,
                    'free_ips' => 0,
                    'total_ips' => 0,
                    'usage_percentage' => 0.0,
                ];
            }

            $utilization[$pool->vlanId]['free_ips'] += $pool->freeIps();
            $utilization[$pool->vlanId]['total_ips'] += $pool->totalIps;
        }

        foreach ($utilization as $vlanId => &$data) {
            $data['usage_percentage'] = $data['total_ips'] > 0
                ? round((($data['total_ips'] - $data['free_ips']) / $data['total_ips']) * 100, 2)
                : 0.0;
        }

        return $utilization;
    }

    /**
     * Check if a VID has available IP pools for new customer assignment.
     */
    public function isVidPoolAvailable(Router $router, int $vlanId, int $minFreeIps = 1, ?string $providerName = null): bool
    {
        $pools = $this->getPoolsByVlan($router, $vlanId, $providerName);

        if ($pools === []) {
            return false;
        }

        foreach ($pools as $pool) {
            if ($pool->freeIps() >= $minFreeIps) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the best VID for new customer based on pool availability.
     * Returns VIDs sorted by available pool capacity (most free first).
     *
     * @return array<int, array{vlan_id:int, free_ips:int, pool_count:int}>
     */
    public function suggestAvailableVids(Router $router, int $minFreeIps = 1, int $limit = 10, ?string $providerName = null): array
    {
        $utilization = $this->getPoolUtilizationByVid($router, $providerName);

        $available = array_filter(
            $utilization,
            static fn (array $data): bool => $data['free_ips'] >= $minFreeIps,
        );

        usort($available, static fn (array $a, array $b): int => $b['free_ips'] <=> $a['free_ips']);

        return array_slice($available, 0, $limit);
    }

    /**
     * Enrich VID records with current pool utilization data from MikroTik.
     *
     * @param  Collection<int, Vid>  $vids
     * @return array<int, array{vlan_id:int, vid: Vid, pool_utilization: array{free_ips:int, total_ips:int, usage_percentage:float, pools: list<MikrotikIpPoolRecord>}}>
     */
    public function enrichVidsWithPoolUtilization(Collection $vids, Router $router, ?string $providerName = null): array
    {
        $pools = $this->fetchFromRouter($router, $providerName);
        $poolsByVlan = [];

        foreach ($pools as $pool) {
            if ($pool->vlanId === null) {
                continue;
            }

            if (! isset($poolsByVlan[$pool->vlanId])) {
                $poolsByVlan[$pool->vlanId] = [];
            }

            $poolsByVlan[$pool->vlanId][] = $pool;
        }

        $enriched = [];

        foreach ($vids as $vid) {
            $vidPools = $poolsByVlan[$vid->vid] ?? [];

            $totalFreeIps = array_reduce(
                $vidPools,
                static fn (int $carry, MikrotikIpPoolRecord $pool): int => $carry + $pool->freeIps(),
                0,
            );

            $totalIps = array_reduce(
                $vidPools,
                static fn (int $carry, MikrotikIpPoolRecord $pool): int => $carry + $pool->totalIps,
                0,
            );

            $enriched[$vid->id] = [
                'vlan_id' => $vid->vid,
                'vid' => $vid,
                'pool_utilization' => [
                    'free_ips' => $totalFreeIps,
                    'total_ips' => $totalIps,
                    'usage_percentage' => $totalIps > 0 ? round((($totalIps - $totalFreeIps) / $totalIps) * 100, 2) : 0.0,
                    'pool_count' => count($vidPools),
                    'pools' => $vidPools,
                ],
            ];
        }

        return $enriched;
    }
}
