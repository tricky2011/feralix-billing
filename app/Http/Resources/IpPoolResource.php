<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IpPoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $usedIps  = $this->resource->usedIps;
        $totalIps = $this->resource->totalIps;
        $freeIps  = $this->resource->freeIps();
        $isFull   = $freeIps === 0;
        $isAvailable = $usedIps === 0 && $freeIps > 0;
        $isReserved  = $usedIps >= 1 && $freeIps > 0;

        // Determine availability status
        $availabilityStatus = match(true) {
            $isFull      => 'full',
            $isReserved  => 'reserved',
            $isAvailable => 'available',
            default      => 'unknown',
        };

        return [
            'name'                => $this->resource->name,
            'ranges'              => $this->resource->ranges,
            'total_ips'           => $totalIps,
            'used_ips'            => $usedIps,
            'free_ips'            => $freeIps,
            'usage_percentage'    => $this->resource->usagePercentage(),
            'is_full'             => $isFull,
            'is_available'        => $isAvailable,
            'is_reserved'         => $isReserved,
            'availability_status' => $availabilityStatus,
            'vlan_id'             => $this->resource->vlanId,
            'vlan_name'           => $this->resource->vlanName,
            'interface'           => $this->resource->interface,
            'dhcp_server_name'    => $this->resource->dhcpServerName,
            'primary_range'       => $this->resource->primaryRangeString(),
            'all_ranges_string'   => $this->resource->allRangesString(),
            'is_complete'         => $this->resource->isComplete(),
        ];
    }
}
