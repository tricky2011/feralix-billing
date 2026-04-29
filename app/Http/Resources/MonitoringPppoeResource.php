<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitoringPppoeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'service_code' => $this->service?->service_code,
            'customer_name' => $this->service?->customer?->full_name,
            'router_id' => $this->router_id,
            'router_name' => $this->router?->router_name,
            'router_code' => $this->router?->router_code,
            'monitor_pppoe_username' => $this->monitor_pppoe_username,
            'status' => $this->status?->value,
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'last_synced_at' => $this->last_synced_at?->toISOString(),
            'last_status_changed_at' => $this->last_status_changed_at?->toISOString(),
        ];
    }
}
