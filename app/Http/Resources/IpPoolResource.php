<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IpPoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource->name,
            'ranges' => $this->resource->ranges,
            'total_ips' => $this->resource->totalIps,
            'used_ips' => $this->resource->usedIps,
            'free_ips' => $this->resource->freeIps(),
            'usage_percentage' => $this->resource->usagePercentage(),
            'is_full' => $this->resource->freeIps() === 0,
            'vlan_id' => $this->resource->vlanId,
            'vlan_name' => $this->resource->vlanName,
            'interface' => $this->resource->interface,
            'dhcp_server_name' => $this->resource->dhcpServerName,
            'primary_range' => $this->resource->primaryRangeString(),
            'all_ranges_string' => $this->resource->allRangesString(),
            'is_complete' => $this->resource->isComplete(),
        ];
    }
}
