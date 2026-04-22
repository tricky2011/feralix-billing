<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'router_id' => $this->router_id,
            'scope_id' => $this->scope_id,
            'vid' => $this->vid,
            'vid_type' => $this->vid_type?->value,
            'subnet_cidr' => $this->subnet_cidr,
            'gateway_ip' => $this->gateway_ip,
            'pool_start_ip' => $this->pool_start_ip,
            'pool_end_ip' => $this->pool_end_ip,
            'pool_ip_count' => $this->pool_ip_count,
            'rate_limit_mbps' => $this->rate_limit_mbps,
            'sync_source' => $this->sync_source,
            'status' => $this->status?->value,
            'customer_id' => $this->customer_id,
            'service_id' => $this->service_id,
            'last_synced_at' => $this->last_synced_at?->toISOString(),
            'router' => $this->whenLoaded('router', function (): array {
                return [
                    'id' => $this->router->id,
                    'router_code' => $this->router->router_code,
                    'router_name' => $this->router->router_name,
                ];
            }),
            'scope' => $this->whenLoaded('routerScope', function (): ?array {
                if ($this->routerScope === null) {
                    return null;
                }

                return [
                    'id' => $this->routerScope->id,
                    'scope_name' => $this->routerScope->scope_name,
                ];
            }),
            'customer' => $this->whenLoaded('customer', function (): ?array {
                if ($this->customer === null) {
                    return null;
                }

                return [
                    'id' => $this->customer->id,
                    'customer_code' => $this->customer->customer_code,
                    'full_name' => $this->customer->full_name,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
