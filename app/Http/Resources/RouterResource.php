<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'api_port' => (int) $this->api_port,
            'timeout' => (int) $this->timeout,
            'use_ssl' => (bool) $this->use_ssl,
            'status' => $status,
            'api_username' => $this->api_username,
            'acs_inform_url' => $this->acs_inform_url,
            'acs_nbi_url' => $this->acs_nbi_url,
            'acs_username' => $this->acs_username,
            'description' => $this->description,
            'has_api_password' => (bool) $this->has_api_password,
            'has_acs_password' => (bool) $this->has_acs_password,
            // Legacy keys kept for backward compatibility in existing pages/modules.
            'router_code' => $this->router_code,
            'router_name' => $this->name,
            'router_role' => $this->router_role,
            'mgmt_ip' => $this->host,
            'location_name' => $this->location_name,
            'is_active' => $status === 'active',
            'scopes_count' => $this->when(isset($this->scopes_count), (int) $this->scopes_count),
            'scopes' => RouterScopeResource::collection($this->whenLoaded('scopes')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
