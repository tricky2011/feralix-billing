<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'router_code' => $this->router_code,
            'router_name' => $this->router_name,
            'router_role' => $this->router_role,
            'mgmt_ip' => $this->mgmt_ip,
            'api_port' => $this->api_port,
            'api_username' => $this->api_username,
            'has_api_password' => $this->api_password !== null,
            'location_name' => $this->location_name,
            'is_active' => $this->is_active,
            'scopes_count' => $this->when(isset($this->scopes_count), (int) $this->scopes_count),
            'scopes' => RouterScopeResource::collection($this->whenLoaded('scopes')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
