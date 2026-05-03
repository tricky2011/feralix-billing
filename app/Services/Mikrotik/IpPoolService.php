<?php

namespace App\Services\Mikrotik;

use App\Contracts\Mikrotik\MikrotikIpPoolProvider;
use App\Data\Mikrotik\MikrotikIpPoolRecord;
use App\Models\Router;
use App\Models\RouterScope;
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
     * Get allowed VID ranges from router scopes.
     * Excludes monitor_vid from each scope.
     *
     * @return array<int, array{vid_start:int, vid_end:int}>
     */
    private function getAllowedVidRanges(Router $router): array
    {
        $scopes = RouterScope::where('router_id', $router->id)->get();

        if ($scopes->isEmpty()) {
            return [];
        }

        $ranges = [];
        foreach ($scopes as $scope) {
            if ($scope->monitor_vid === null) {
                $ranges[] = [
                    'vid_start' => $scope->vid_start,
                    'vid_end'   => $scope->vid_end,
                ];
            } else {
                // Split into two ranges if monitor_vid is in the middle
                if ($scope->monitor_vid > $scope->vid_start) {
                    $ranges[] = [
                        'vid_start' => $scope->vid_start,
                        'vid_end'   => $scope->monitor_vid - 1,
                    ];
                }
                if ($scope->monitor_vid < $scope->vid_end) {
                    $ranges[] = [
                        'vid_start' => $scope->monitor_vid + 1,
                        'vid_end'   => $scope->vid_end,
                    ];
                }
            }
        }

        return $ranges;
    }

    /**
     * Suggest VIDs available for new customer assignment.
     * Rule: used_ips MUST be 0 (completely unused), within router scopes (exclude monitor_vid).
     *
     * @return array<int, array{vlan_id:int, free_ips:int, total_ips:int, used_ips:int, pool_name:string, usage_percentage:float}>
     */
    public function suggestAvailableVids(Router $router, int $minFreeIps = 1, int $limit = 10, ?string $providerName = null): array
    {
        $pools = $this->fetchFromRouter($router, $providerName);
        $ranges = $this->getAllowedVidRanges($router);

        $available = [];

        foreach ($pools as $pool) {
            if ($pool->vlanId === null) {
                continue;
            }

            // Filter by scope ranges
            if (! empty($ranges)) {
                $inRange = false;
                foreach ($ranges as $range) {
                    if ($pool->vlanId >= $range['vid_start'] && $pool->vlanId <= $range['vid_end']) {
                        $inRange = true;
                        break;
                    }
                }
                if (! $inRange) {
                    continue;
                }
            } else {
                // No scopes defined, skip
                continue;
            }

            // used_ips harus 0 — jika >= 1 berarti sudah dipakai pelanggan
            if ($pool->usedIps > 0) {
                continue;
            }

            // Harus punya free IPs
            if ($pool->freeIps() < $minFreeIps) {
                continue;
            }

            $available[] = [
                'vlan_id'          => $pool->vlanId,
                'free_ips'         => $pool->freeIps(),
                'total_ips'        => $pool->totalIps,
                'used_ips'         => $pool->usedIps,
                'pool_name'        => $pool->name,
                'usage_percentage' => $pool->usagePercentage(),
                'pool_count'       => 1,
            ];
        }

        // Sort ascending by vlan_id (assign dari yang terkecil dulu)
        usort($available, static fn (array $a, array $b): int => $a['vlan_id'] <=> $b['vlan_id']);

        return array_slice($available, 0, $limit);
    }

    /**
     * Check if a VID has available IP pools for new customer assignment.
     * Rule: used_ips MUST be 0, within router scopes (exclude monitor_vid).
     */
    public function isVidPoolAvailable(Router $router, int $vlanId, int $minFreeIps = 1, ?string $providerName = null): bool
    {
        $ranges = $this->getAllowedVidRanges($router);

        // Check if VID is in allowed ranges
        if (! empty($ranges)) {
            $inRange = false;
            foreach ($ranges as $range) {
                if ($vlanId >= $range['vid_start'] && $vlanId <= $range['vid_end']) {
                    $inRange = true;
                    break;
                }
            }
            if (! $inRange) {
                return false;
            }
        } else {
            // No scopes defined
            return false;
        }

        $pools = $this->getPoolsByVlan($router, $vlanId, $providerName);

        foreach ($pools as $pool) {
            if ($pool->usedIps === 0 && $pool->freeIps() >= $minFreeIps) {
                return true;
            }
        }

        return false;
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
