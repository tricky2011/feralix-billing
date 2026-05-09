<?php

namespace App\Services\Mikrotik;

use App\Models\IpPoolSnapshot;
use App\Models\Router;
use App\Models\RouterScope;
use Illuminate\Support\Facades\DB;

class IpPoolSyncService
{
    public function __construct(
        private readonly IpPoolService $ipPoolService,
    ) {}

    /**
     * Fetch all pools from Mikrotik live WITHOUT saving to DB.
     * Used for preview/selection UI.
     *
     * @return array<int, array{pool_name:string, vlan_id:int|null, interface:string|null, total_ips:int, used_ips:int, free_ips:int, availability_status:string, is_tracked:bool}>
     */
    public function previewFromMikrotik(Router $router): array
    {
        $pools = $this->ipPoolService->fetchFromRouter($router);

        // Get currently tracked pool names
        $trackedNames = IpPoolSnapshot::where('router_id', $router->id)
            ->where('is_tracked', true)
            ->pluck('pool_name')
            ->flip()
            ->all();

        return array_map(static function ($pool) use ($trackedNames): array {
            $usedIps  = $pool->usedIps;
            $freeIps  = $pool->freeIps();
            $isFull   = $freeIps === 0;
            $isAvailable = $usedIps === 0 && $freeIps > 0;
            $isReserved  = $usedIps >= 1 && $freeIps > 0;

            $status = match(true) {
                $isFull      => 'full',
                $isReserved  => 'reserved',
                $isAvailable => 'available',
                default      => 'unknown',
            };

            return [
                'pool_name'           => $pool->name,
                'vlan_id'             => $pool->vlanId,
                'vlan_name'           => $pool->vlanName,
                'interface'           => $pool->interface,
                'dhcp_server_name'    => $pool->dhcpServerName,
                'primary_range'       => $pool->primaryRangeString(),
                'total_ips'           => $pool->totalIps,
                'used_ips'            => $usedIps,
                'free_ips'            => $freeIps,
                'usage_percentage'    => $pool->usagePercentage(),
                'availability_status' => $status,
                'is_tracked'          => isset($trackedNames[$pool->name]),
            ];
        }, $pools);
    }

    /**
     * Save selected pool names to DB as tracked pools.
     * Untracked pools are kept in DB but marked is_tracked=false.
     *
     * @param  string[]  $selectedPoolNames
     */
    public function saveSelection(Router $router, array $selectedPoolNames): int
    {
        $pools = $this->ipPoolService->fetchFromRouter($router);
        $syncedAt = now();
        $count = 0;

        DB::transaction(function () use ($router, $pools, $selectedPoolNames, $syncedAt, &$count): void {
            $selectedSet = array_flip($selectedPoolNames);

            foreach ($pools as $pool) {
                $isTracked = isset($selectedSet[$pool->name]);

                // Check if this VID has a confirmed reservation
                $existing = IpPoolSnapshot::where('router_id', $router->id)
                    ->where('pool_name', $pool->name)
                    ->first();

                $isConfirmedReservation = $existing && $existing->reserved_by_customer_id !== null;

                $usedIps  = $pool->usedIps;
                $freeIps  = $pool->freeIps();
                $isFull   = $freeIps === 0;
                $isAvailable = $usedIps === 0 && $freeIps > 0;
                $isReserved  = $usedIps >= 1 && $freeIps > 0;

                // Respect confirmed reservations — don't reset to Mikrotik state
                if ($isConfirmedReservation) {
                    $usedIps  = max($pool->usedIps, 1);
                    $freeIps  = min($pool->freeIps(), $pool->totalIps - 1);
                    $isFull   = $freeIps === 0;
                    $isAvailable = false;
                    $isReserved  = true;
                }

                $status = match(true) {
                    $isFull      => 'full',
                    $isReserved  => 'reserved',
                    $isAvailable => 'available',
                    default      => 'unknown',
                };

                IpPoolSnapshot::updateOrCreate(
                    ['router_id' => $router->id, 'pool_name' => $pool->name],
                    [
                        'vlan_id'             => $pool->vlanId,
                        'vlan_name'           => $pool->vlanName,
                        'interface'           => $pool->interface,
                        'dhcp_server_name'    => $pool->dhcpServerName,
                        'primary_range'       => $pool->primaryRangeString(),
                        'total_ips'           => $pool->totalIps,
                        'used_ips'            => $usedIps,
                        'free_ips'            => $freeIps,
                        'usage_percentage'    => $pool->usagePercentage(),
                        'is_full'             => $isFull,
                        'is_available'        => $isAvailable,
                        'is_reserved'         => $isReserved,
                        'availability_status' => $status,
                        'is_tracked'          => $isTracked,
                        'synced_at'           => $syncedAt,
                    ]
                );

                if ($isTracked) {
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * Sync tracked pools from Mikrotik to DB.
     */
    public function syncFromMikrotik(Router $router): int
    {
        $trackedNames = IpPoolSnapshot::where('router_id', $router->id)
            ->where('is_tracked', true)
            ->pluck('pool_name')
            ->all();

        // If no tracked pools, sync all
        if (empty($trackedNames)) {
            $pools = $this->ipPoolService->fetchFromRouter($router);
        } else {
            $allPools = $this->ipPoolService->fetchFromRouter($router);
            $trackedSet = array_flip($trackedNames);
            $pools = array_filter($allPools, static fn ($p) => isset($trackedSet[$p->name]));
        }

        if (empty($pools)) {
            return 0;
        }

        $syncedAt = now();
        $count = 0;

        DB::transaction(function () use ($router, $pools, $syncedAt, &$count): void {
            foreach ($pools as $pool) {
                // Check if this VID has a confirmed reservation
                $existing = IpPoolSnapshot::where('router_id', $router->id)
                    ->where('pool_name', $pool->name)
                    ->first();

                $isConfirmedReservation = $existing && $existing->reserved_by_customer_id !== null;

                $usedIps  = $pool->usedIps;
                $freeIps  = $pool->freeIps();
                $isFull   = $freeIps === 0;
                $isAvailable = $usedIps === 0 && $freeIps > 0;
                $isReserved  = $usedIps >= 1 && $freeIps > 0;

                // Respect confirmed reservations — don't reset to Mikrotik state
                if ($isConfirmedReservation) {
                    $usedIps  = max($pool->usedIps, 1);
                    $freeIps  = min($pool->freeIps(), $pool->totalIps - 1);
                    $isFull   = $freeIps === 0;
                    $isAvailable = false;
                    $isReserved  = true;
                }

                $status = match(true) {
                    $isFull      => 'full',
                    $isReserved  => 'reserved',
                    $isAvailable => 'available',
                    default      => 'unknown',
                };

                IpPoolSnapshot::updateOrCreate(
                    ['router_id' => $router->id, 'pool_name' => $pool->name],
                    [
                        'vlan_id'             => $pool->vlanId,
                        'vlan_name'           => $pool->vlanName,
                        'interface'           => $pool->interface,
                        'dhcp_server_name'    => $pool->dhcpServerName,
                        'primary_range'       => $pool->primaryRangeString(),
                        'total_ips'           => $pool->totalIps,
                        'used_ips'            => $usedIps,
                        'free_ips'            => $freeIps,
                        'usage_percentage'    => $pool->usagePercentage(),
                        'is_full'             => $isFull,
                        'is_available'        => $isAvailable,
                        'is_reserved'         => $isReserved,
                        'availability_status' => $status,
                        'is_tracked'          => true,
                        'synced_at'           => $syncedAt,
                    ]
                );
                $count++;
            }
        });

        return $count;
    }

    /**
     * Get pools from DB (tracked only).
     */
    public function getFromCache(Router $router): array
    {
        return IpPoolSnapshot::where('router_id', $router->id)
            ->where('is_tracked', true)
            ->orderBy('vlan_id')
            ->orderBy('pool_name')
            ->get()
            ->all();
    }

    /**
     * Suggest VID available dan langsung lock dengan SELECT FOR UPDATE + temporary reservation.
     * Permanent reservation — tidak ada expiry.
     * VID lepas hanya saat customer di-terminate.
     *
     * @return array{vlan_id:int, pool_name:string, primary_range:string|null, free_ips:int, total_ips:int}|null
     */
    public function suggestAndLockVid(Router $router, int $minFreeIps = 1): ?array
    {
        $this->ensureCache($router);

        $scopes = RouterScope::where('router_id', $router->id)
            ->where('is_special', false)
            ->orderBy('vid_start')
            ->get();

        return DB::transaction(function () use ($router, $minFreeIps, $scopes): ?array {
            $query = IpPoolSnapshot::where('router_id', $router->id)
                ->where('is_tracked', true)
                ->where('used_ips', 0)
                ->where('free_ips', '>=', $minFreeIps)
                ->whereNotNull('vlan_id')
                ->whereNull('reserved_by_customer_id')
                ->where('availability_status', 'available');

            if ($scopes->isNotEmpty()) {
                $query->where(function ($q) use ($scopes): void {
                    foreach ($scopes as $scope) {
                        $q->orWhere(function ($sub) use ($scope): void {
                            $sub->where('vlan_id', '>=', $scope->vid_start)
                                ->where('vlan_id', '<=', $scope->vid_end);
                            if ($scope->monitor_vid !== null) {
                                $sub->where('vlan_id', '!=', $scope->monitor_vid);
                            }
                        });
                    }
                });
            }

            // SELECT FOR UPDATE — prevent concurrent reservation
            $snapshot = $query->orderBy('vlan_id')->lockForUpdate()->first();

            if ($snapshot === null) {
                return null;
            }

            // Mark as temporarily reserved (used_ips = 1, is_reserved = true)
            // This prevents other transactions from picking the same VID
            $snapshot->update([
                'used_ips'            => 1,
                'free_ips'            => $snapshot->total_ips - 1,
                'is_available'        => false,
                'is_reserved'         => true,
                'availability_status' => 'reserved',
            ]);

            $matchingScope = $scopes->first(
                static fn ($s) => $snapshot->vlan_id >= $s->vid_start && $snapshot->vlan_id <= $s->vid_end
            );

            return [
                'vlan_id'       => $snapshot->vlan_id,
                'vid'           => $snapshot->vlan_id,
                'vid_number'    => $snapshot->vlan_id,
                'internet_vid'  => $snapshot->vlan_id,
                'monitor_vid'   => null,
                'pool_name'     => $snapshot->pool_name,
                'primary_range' => $snapshot->primary_range,
                'ip_start'      => $snapshot->primary_range ? explode('-', $snapshot->primary_range)[0] : null,
                'ip_end'        => $snapshot->primary_range ? explode('-', $snapshot->primary_range)[1] : null,
                'free_ips'      => $snapshot->total_ips - 1,
                'total_ips'     => $snapshot->total_ips,
                'used_ips'      => 1,
                'scope_name'    => $matchingScope?->scope_name,
            ];
        });
    }

    /**
     * Ensure router has cached pools in DB.
     */
    private function ensureCache(Router $router): void
    {
        $hasTracked = IpPoolSnapshot::where('router_id', $router->id)
            ->where('is_tracked', true)
            ->exists();

        if (!$hasTracked) {
            $this->syncFromMikrotik($router);
        }
    }

    /**
     * Confirm reservation — link VID ke customer.
     * Dipanggil setelah customer berhasil dibuat.
     */
    public function confirmVidForCustomer(int $routerId, int $vlanId, int $customerId): void
    {
        IpPoolSnapshot::where('router_id', $routerId)
            ->where('vlan_id', $vlanId)
            ->update([
                'reserved_by_customer_id' => $customerId,
                'is_available'            => false,
                'is_reserved'             => true,
                'availability_status'     => 'reserved',
                'used_ips'                => DB::raw('GREATEST(used_ips + 1, 1)'),
                'free_ips'                => DB::raw('GREATEST(free_ips - 1, 0)'),
            ]);
    }

    /**
     * Release VID saat customer di-terminate.
     * Dipanggil dari CustomerTerminationService.
     */
    public function releaseVidForCustomer(int $customerId): void
    {
        IpPoolSnapshot::where('reserved_by_customer_id', $customerId)
            ->update([
                'reserved_by_customer_id' => null,
                'is_available'            => true,
                'is_reserved'             => false,
                'availability_status'     => 'available',
                'used_ips'                => DB::raw('GREATEST(used_ips - 1, 0)'),
                'free_ips'                => DB::raw('free_ips + 1'),
            ]);
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
            // Exclude monitor_vid from range
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
     * Check if a VID is within allowed ranges (excluding monitor_vid).
     */
    private function isVidAllowed(int $vlanId, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($vlanId >= $range['vid_start'] && $vlanId <= $range['vid_end']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Suggest available VIDs from tracked pools in DB.
     * Filters based on router scopes (vid_start, vid_end, exclude monitor_vid).
     */
    public function suggestAvailableVids(
        Router $router,
        int $minFreeIps = 1,
        int $limit = 10,
    ): array {
        $ranges = $this->getAllowedVidRanges($router);

        if (empty($ranges)) {
            // No scopes defined, return empty
            return [];
        }

        $query = IpPoolSnapshot::where('router_id', $router->id)
            ->where('is_tracked', true)
            ->where('used_ips', 0)
            ->where('free_ips', '>=', $minFreeIps);

        // Build dynamic WHERE clause for allowed VID ranges
        $query = $query->where(function ($q) use ($ranges) {
            foreach ($ranges as $i => $range) {
                if ($i === 0) {
                    $q->whereBetween('vlan_id', [$range['vid_start'], $range['vid_end']]);
                } else {
                    $q->orWhereBetween('vlan_id', [$range['vid_start'], $range['vid_end']]);
                }
            }
        });

        return $query
            ->orderBy('vlan_id')
            ->limit($limit)
            ->get()
            ->map(static fn (IpPoolSnapshot $s): array => [
                'vlan_id'          => $s->vlan_id,
                'free_ips'         => $s->free_ips,
                'total_ips'        => $s->total_ips,
                'used_ips'         => $s->used_ips,
                'pool_name'        => $s->pool_name,
                'usage_percentage' => $s->usage_percentage,
                'pool_count'       => 1,
            ])
            ->all();
    }
}
