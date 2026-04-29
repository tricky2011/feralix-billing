<?php

namespace App\Services\Mikrotik;

use App\Data\Mikrotik\MikrotikIpPoolRecord;
use App\Models\Router;
use App\Models\RouterScope;
use App\Models\Vid;
use Illuminate\Support\Collection;

class IpPoolVidAssignmentService
{
    public function __construct(
        private readonly IpPoolService $ipPoolService,
    ) {}

    /**
     * Get suggested VIDs for customer assignment based on IP pool availability.
     * Returns VIDs that have available IPs in their associated pools.
     *
     * @param  Router  $router  The router to search for available VIDs
     * @param  int  $minFreeIps  Minimum free IPs required (default: 1)
     * @param  int  $limit  Maximum number of suggestions to return (default: 10)
     * @return array<int, array{vlan_id:int, pool_name:string|null, free_ips:int, total_ips:int, usage_percentage:float, vid: Vid|null}>
     */
    public function suggestVidsForAssignment(
        Router $router,
        int $minFreeIps = 1,
        int $limit = 10,
    ): array {
        $suggestions = $this->ipPoolService->suggestAvailableVids($router, $minFreeIps, $limit);

        $vidMap = $this->buildVidMap($router);

        $result = [];

        foreach ($suggestions as $suggestion) {
            $vlanId = $suggestion['vlan_id'];
            $vid = $vidMap[$vlanId] ?? null;

            $pools = $this->ipPoolService->getPoolsByVlan($router, $vlanId);

            $primaryPool = $pools[0] ?? null;

            $result[] = [
                'vlan_id' => $vlanId,
                'pool_name' => $primaryPool?->name,
                'free_ips' => $suggestion['free_ips'],
                'total_ips' => $suggestion['total_ips'],
                'usage_percentage' => $suggestion['total_ips'] > 0
                    ? round((($suggestion['total_ips'] - $suggestion['free_ips']) / $suggestion['total_ips']) * 100, 2)
                    : 0.0,
                'vid' => $vid,
                'vid_id' => $vid?->id,
                'vid_status' => $vid?->status?->value,
                'vid_type' => $vid?->vid_type?->value,
                'scope_name' => $vid?->routerScope?->scope_name,
            ];
        }

        return $result;
    }

    /**
     * Check if a specific VID is available for customer assignment.
     */
    public function isVidAvailableForAssignment(
        Router $router,
        int $vlanId,
        int $minFreeIps = 1,
    ): bool {
        $isPoolAvailable = $this->ipPoolService->isVidPoolAvailable($router, $vlanId, $minFreeIps);

        if (! $isPoolAvailable) {
            return false;
        }

        $vid = $this->findVidByVlan($router, $vlanId);

        if ($vid === null) {
            return false;
        }

        return $vid->status?->value === 'available';
    }

    /**
     * Find the best available pool for a specific VID.
     */
    public function findBestPoolForVid(
        Router $router,
        int $vlanId,
        int $minFreeIps = 1,
    ): ?MikrotikIpPoolRecord {
        $pools = $this->ipPoolService->getPoolsByVlan($router, $vlanId);

        if ($pools === []) {
            return null;
        }

        $availablePools = array_filter(
            $pools,
            static fn (MikrotikIpPoolRecord $pool): bool => $pool->freeIps() >= $minFreeIps,
        );

        if ($availablePools === []) {
            return null;
        }

        usort($availablePools, static fn (MikrotikIpPoolRecord $a, MikrotikIpPoolRecord $b): int => $b->freeIps() <=> $a->freeIps());

        return array_values($availablePools)[0];
    }

    /**
     * Get complete assignment data for a VID including network details.
     *
     * @return array{vlan_id:int, vid:Vid|null, pool:MikrotikIpPoolRecord|null, is_available:bool, network_details:array}
     */
    public function getVidAssignmentData(
        Router $router,
        int $vlanId,
        int $minFreeIps = 1,
    ): array {
        $vid = $this->findVidByVlan($router, $vlanId);
        $pool = $this->findBestPoolForVid($router, $vlanId, $minFreeIps);

        $isPoolAvailable = $pool !== null;
        $isVidAvailable = $vid !== null && $vid->status?->value === 'available';
        $isAvailable = $isPoolAvailable && $isVidAvailable;

        $networkDetails = [];

        if ($vid !== null) {
            $networkDetails = [
                'subnet_cidr' => $vid->subnet_cidr,
                'gateway_ip' => $vid->gateway_ip,
                'pool_start_ip' => $vid->pool_start_ip,
                'pool_end_ip' => $vid->pool_end_ip,
                'ip_pool_count' => $vid->pool_ip_count,
            ];
        } elseif ($pool !== null) {
            $primaryRange = $pool->primaryRange();
            $networkDetails = [
                'pool_range' => $pool->primaryRangeString(),
                'pool_start_ip' => $primaryRange['start_ip'] ?? null,
                'pool_end_ip' => $primaryRange['end_ip'] ?? null,
            ];
        }

        return [
            'vlan_id' => $vlanId,
            'vid' => $vid,
            'vid_id' => $vid?->id,
            'pool' => $pool,
            'is_available' => $isAvailable,
            'pool_available' => $isPoolAvailable,
            'vid_available' => $isVidAvailable,
            'network_details' => $networkDetails,
            'pool_utilization' => [
                'free_ips' => $pool?->freeIps() ?? 0,
                'total_ips' => $pool?->totalIps ?? 0,
                'used_ips' => $pool?->usedIps ?? 0,
                'usage_percentage' => $pool?->usagePercentage() ?? 0.0,
            ],
        ];
    }

    /**
     * Auto-select the best VID for new customer assignment.
     *
     * @return array{vlan_id:int, vid:Vid, pool:MikrotikIpPoolRecord, network_details:array}|null
     */
    public function autoSelectVidForCustomer(
        Router $router,
        int $minFreeIps = 1,
    ): ?array {
        $suggestions = $this->suggestVidsForAssignment($router, $minFreeIps, 1);

        if ($suggestions === []) {
            return null;
        }

        $suggestion = $suggestions[0];

        if ($suggestion['vid'] === null) {
            return null;
        }

        $pool = $this->findBestPoolForVid($router, $suggestion['vlan_id'], $minFreeIps);

        if ($pool === null) {
            return null;
        }

        $primaryRange = $pool->primaryRange();

        return [
            'vlan_id' => $suggestion['vlan_id'],
            'vid' => $suggestion['vid'],
            'pool' => $pool,
            'network_details' => [
                'subnet_cidr' => $suggestion['vid']->subnet_cidr,
                'gateway_ip' => $suggestion['vid']->gateway_ip,
                'pool_start_ip' => $primaryRange['start_ip'] ?? $suggestion['vid']->pool_start_ip,
                'pool_end_ip' => $primaryRange['end_ip'] ?? $suggestion['vid']->pool_end_ip,
                'ip_pool_count' => $suggestion['vid']->pool_ip_count ?? $pool->totalIps,
            ],
        ];
    }

    /**
     * Get pool utilization overview for all VIDs in a router scope.
     *
     * @return array<int, array{vlan_id:int, vid:Vid|null, pools:array, utilization:array}>
     */
    public function getRouterScopeUtilization(RouterScope $routerScope): array
    {
        $router = $routerScope->router;

        $vids = Vid::query()
            ->where('router_id', $router->id)
            ->whereBetween('vid', [$routerScope->vid_start, $routerScope->vid_end])
            ->with(['routerScope:id,router_id,scope_name'])
            ->get();

        $result = [];

        for ($vlanId = $routerScope->vid_start; $vlanId <= $routerScope->vid_end; $vlanId++) {
            $vid = $vids->first(fn (Vid $v): bool => $v->vid === $vlanId);
            $pools = $this->ipPoolService->getPoolsByVlan($router, $vlanId);

            $totalFreeIps = array_reduce(
                $pools,
                static fn (int $carry, MikrotikIpPoolRecord $pool): int => $carry + $pool->freeIps(),
                0,
            );

            $totalIps = array_reduce(
                $pools,
                static fn (int $carry, MikrotikIpPoolRecord $pool): int => $carry + $pool->totalIps,
                0,
            );

            $result[$vlanId] = [
                'vlan_id' => $vlanId,
                'vid' => $vid,
                'vid_id' => $vid?->id,
                'vid_status' => $vid?->status?->value,
                'pools' => $pools,
                'utilization' => [
                    'pool_count' => count($pools),
                    'total_ips' => $totalIps,
                    'free_ips' => $totalFreeIps,
                    'used_ips' => $totalIps - $totalFreeIps,
                    'usage_percentage' => $totalIps > 0 ? round((($totalIps - $totalFreeIps) / $totalIps) * 100, 2) : 0.0,
                ],
            ];
        }

        return $result;
    }

    /**
     * @return array<int, Vid>
     */
    private function buildVidMap(Router $router): array
    {
        return Vid::query()
            ->where('router_id', $router->id)
            ->with(['routerScope:id,router_id,scope_name'])
            ->get()
            ->keyBy('vid')
            ->toArray();
    }

    private function findVidByVlan(Router $router, int $vlanId): ?Vid
    {
        return Vid::query()
            ->where('router_id', $router->id)
            ->where('vid', $vlanId)
            ->with(['routerScope:id,router_id,scope_name'])
            ->first();
    }
}
