<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Data\Mikrotik\MikrotikIpPoolRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\IpPool\FetchIpPoolRequest;
use App\Http\Requests\IpPool\IndexIpPoolRequest;
use App\Http\Resources\IpPoolResource;
use App\Models\IpPoolSnapshot;
use App\Models\Router;
use App\Models\Vid;
use App\Services\Mikrotik\IpPoolService;
use App\Services\Mikrotik\IpPoolSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IpPoolController extends Controller
{
    public function __construct(
        private readonly IpPoolService $ipPoolService,
        private readonly IpPoolSyncService $ipPoolSyncService,
    ) {}

    public function index(IndexIpPoolRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $routerId = (int) ($validated['router_id'] ?? 0);

        $router = Router::query()->findOrFail($routerId);

        $hasTracked = IpPoolSnapshot::where('router_id', $router->id)
            ->where('is_tracked', true)
            ->exists();

        // If no tracked pools, fetch directly from Mikrotik
        if (!$hasTracked) {
            $pools = $this->ipPoolService->fetchFromRouter($router);

            // Apply filters
            if (!empty($validated['vlan_id'])) {
                $vlanId = (int) $validated['vlan_id'];
                $pools  = array_values(array_filter($pools, static fn ($p) => $p->vlanId === $vlanId));
            }

            if (!empty($validated['available_only'])) {
                $minFree = max(1, (int) ($validated['min_free_ips'] ?? 1));
                $pools   = array_values(array_filter($pools, static fn ($p) => $p->freeIps() >= $minFree));
            }

            return $this->collectionResponse(
                $pools,
                IpPoolResource::class,
                'IP pools retrieved from Mikrotik (no tracked pools yet).',
                [
                    'router_id'  => $router->id,
                    'source'     => 'mikrotik',
                    'synced_at'  => null,
                    'has_tracked'=> false,
                ],
            );
        }

        // Read from DB cache
        $query = IpPoolSnapshot::where('router_id', $router->id)
            ->where('is_tracked', true);

        if (!empty($validated['vlan_id'])) {
            $query->where('vlan_id', (int) $validated['vlan_id']);
        }

        if (!empty($validated['available_only'])) {
            $minFree = max(1, (int) ($validated['min_free_ips'] ?? 1));
            $query->where('free_ips', '>=', $minFree)
                  ->where('is_full', false);
        }

        $snapshots = $query->orderBy('vlan_id')->orderBy('pool_name')->get();
        $syncedAt  = IpPoolSnapshot::where('router_id', $router->id)->min('synced_at');

        // Convert snapshot to MikrotikIpPoolRecord for IpPoolResource
        $pools = $snapshots->map(function (IpPoolSnapshot $s): MikrotikIpPoolRecord {
            $ranges = [];
            if (!empty($s->primary_range)) {
                $parts = explode('-', $s->primary_range, 2);
                $ranges[] = [
                    'start_ip' => $parts[0] ?? '',
                    'end_ip'   => $parts[1] ?? '',
                ];
            }
            return new MikrotikIpPoolRecord([
                'name'            => $s->pool_name,
                'ranges'          => $ranges,
                'total_ips'       => $s->total_ips,
                'used_ips'        => $s->used_ips,
                'vlan_id'         => $s->vlan_id,
                'vlan_name'       => $s->vlan_name,
                'interface'       => $s->interface,
                'dhcp_server_name'=> $s->dhcp_server_name,
            ]);
        })->all();

        return $this->collectionResponse(
            $pools,
            IpPoolResource::class,
            'IP pools retrieved successfully.',
            [
                'router_id'   => $router->id,
                'source'      => 'cache',
                'synced_at'   => $syncedAt,
                'has_tracked' => true,
                'total_pools' => count($pools),
            ],
        );
    }

    public function show(FetchIpPoolRequest $request, Router $router): JsonResponse
    {
        $validated = $request->validated();

        $pools = $this->ipPoolService->fetchFromRouter($router);

        if (isset($validated['pool_name'])) {
            $pools = array_values(array_filter(
                $pools,
                static fn ($pool): bool => $pool->name === $validated['pool_name'],
            ));
        }

        if (isset($validated['vlan_id'])) {
            $vlanId = (int) $validated['vlan_id'];
            $pools = $this->ipPoolService->getPoolsByVlan($router, $vlanId);
        }

        if (isset($validated['available_only']) && filter_var($validated['available_only'], FILTER_VALIDATE_BOOLEAN)) {
            $minFreeIps = max(1, (int) ($validated['min_free_ips'] ?? 1));
            $pools = $this->ipPoolService->getAvailablePools($router, $minFreeIps);
        }

        return $this->collectionResponse(
            $pools,
            IpPoolResource::class,
            'IP pools retrieved successfully.',
            [
                'router_id' => $router->id,
                'router_code' => $router->router_code,
                'total' => count($pools),
            ],
        );
    }

    public function summary(FetchIpPoolRequest $request, Router $router): JsonResponse
    {
        $request->validate([]);
        $validated = $request->validated();

        $summary = $this->ipPoolService->getPoolStatusSummary($router);

        return $this->successResponse(
            'IP pool summary retrieved successfully.',
            $summary,
        );
    }

    public function utilization(FetchIpPoolRequest $request, Router $router): JsonResponse
    {
        $validated = $request->validated();
        $minFreeIps = max(1, (int) ($validated['min_free_ips'] ?? 1));
        $limit = max(1, min((int) ($validated['limit'] ?? 10), 100));

        $utilization = $this->ipPoolService->getPoolUtilizationByVid($router);

        $availableVids = $this->ipPoolService->suggestAvailableVids($router, $minFreeIps, $limit);

        return $this->successResponse(
            'IP pool utilization by VID retrieved successfully.',
            [
                'router_id' => $router->id,
                'router_code' => $router->router_code,
                'pool_utilization_by_vid' => array_values($utilization),
                'suggested_vids_for_assignment' => $availableVids,
            ],
        );
    }

    public function suggestForVid(FetchIpPoolRequest $request, Router $router): JsonResponse
    {
        $validated = $request->validated();
        $vlanId = (int) ($validated['vlan_id'] ?? 0);
        $minFreeIps = max(1, (int) ($validated['min_free_ips'] ?? 1));

        if ($vlanId < 1 || $vlanId > 4094) {
            return $this->successResponse(
                'Invalid VLAN ID.',
                ['vlan_id' => $vlanId, 'pools' => [], 'is_available' => false],
            );
        }

        $isAvailable = $this->ipPoolService->isVidPoolAvailable($router, $vlanId, $minFreeIps);
        $pools = $this->ipPoolService->findPoolsForVid($router, $vlanId, $minFreeIps);

        return $this->successResponse(
            'IP pool suggestions for VID retrieved successfully.',
            [
                'vlan_id' => $vlanId,
                'is_available' => $isAvailable,
                'pools' => IpPoolResource::collection($pools),
            ],
        );
    }

    public function suggest(Request $request): JsonResponse
    {
        $request->validate(['router_id' => 'required|exists:routers,id']);

        $router = Router::findOrFail((int) $request->router_id);

        $suggestions = $this->ipPoolService->suggestAvailableVids($router, 1, 1);

        if (empty($suggestions)) {
            return $this->successResponse('No available VID found.', null);
        }

        $first = $suggestions[0];

        // Parse ip_start and ip_end from primary_range (format: "x.x.x.x-x.x.x.x")
        $primaryRange = $first['pool_name'] ?? null;
        $ipStart = null;
        $ipEnd   = null;
        if (!empty($primaryRange) && str_contains($primaryRange, '-')) {
            $parts   = explode('-', $primaryRange, 2);
            $ipStart = trim($parts[0]);
            $ipEnd   = trim($parts[1]);
        }

        return $this->successResponse('VID suggestion retrieved successfully.', [
            'vlan_id'       => $first['vlan_id'],
            'vid'           => $first['vlan_id'],
            'vid_number'    => $first['vlan_id'],
            'internet_vid'  => $first['vlan_id'],
            'monitor_vid'   => null,
            'pool_name'     => $first['pool_name'],
            'primary_range' => $first['primary_range'] ?? $first['pool_name'] ?? null,
            'ip_start'      => $ipStart,
            'ip_end'        => $ipEnd,
            'free_ips'      => $first['free_ips'],
            'total_ips'     => $first['total_ips'],
            'used_ips'      => $first['used_ips'],
        ]);
    }

    public function vidsWithAvailability(FetchIpPoolRequest $request, Router $router): JsonResponse
    {
        $request->validate([]);
        $validated = $request->validated();

        $minFreeIps = max(1, (int) ($validated['min_free_ips'] ?? 1));

        $vids = Vid::query()
            ->where('router_id', $router->id)
            ->whereIn('vid_type', ['customer_internet', 'CustomerInternet'])
            ->orderBy('vid')
            ->get();

        $enriched = $this->ipPoolService->enrichVidsWithPoolUtilization($vids, $router);

        $result = [];

        foreach ($enriched as $vidId => $data) {
            /** @var Vid $vid */
            $vid = $data['vid'];
            $utilization = $data['pool_utilization'];
            $pools = $utilization['pools'] ?? [];

            $vlanName = null;
            foreach ($pools as $pool) {
                if ($pool->vlanName !== null) {
                    $vlanName = $pool->vlanName;
                    break;
                }
            }

            $result[] = [
                'vid_id' => $vid->id,
                'vid' => $vid->vid,
                'vlan_name' => $vlanName,
                'status' => $vid->status?->value,
                'is_available' => $utilization['free_ips'] >= $minFreeIps,
                'pool_utilization' => [
                    'free_ips' => $utilization['free_ips'],
                    'total_ips' => $utilization['total_ips'],
                    'usage_percentage' => $utilization['usage_percentage'],
                    'pool_count' => $utilization['pool_count'],
                ],
                'pools' => IpPoolResource::collection(collect($pools)),
            ];
        }

        usort($result, static fn (array $a, array $b): int => $b['pool_utilization']['free_ips'] <=> $a['pool_utilization']['free_ips']);

        return $this->successResponse(
            'VIDs with pool availability retrieved successfully.',
            [
                'router_id' => $router->id,
                'router_code' => $router->router_code,
                'vids' => $result,
            ],
        );
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate(['router_id' => 'required|exists:routers,id']);

        $router  = Router::findOrFail((int) $request->router_id);
        $preview = $this->ipPoolSyncService->previewFromMikrotik($router);

        return $this->successResponse('IP pools preview retrieved successfully.', $preview);
    }

    public function saveSelection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'router_id'   => 'required|exists:routers,id',
            'pool_names'  => 'required|array|min:1',
            'pool_names.*' => 'required|string|max:100',
        ]);

        $router = Router::findOrFail((int) $validated['router_id']);
        $count  = $this->ipPoolSyncService->saveSelection($router, $validated['pool_names']);

        return $this->successResponse('Pool selection saved successfully.', [
            'router_id'     => $router->id,
            'tracked_count' => $count,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $request->validate(['router_id' => 'required|exists:routers,id']);

        $router = Router::findOrFail((int) $request->router_id);
        $count  = $this->ipPoolSyncService->syncFromMikrotik($router);

        return $this->successResponse('IP pools synced successfully.', [
            'router_id' => $router->id,
            'synced_count' => $count,
        ]);
    }
}
