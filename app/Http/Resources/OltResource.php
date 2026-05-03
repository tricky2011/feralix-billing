<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OltResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resolvedName = $this->name ?? $this->olt_name;
        $resolvedCode = $this->code ?? $this->olt_code;
        $resolvedHost = $this->host ?? $this->mgmt_ip;
        $resolvedBrand = $this->brand ?? $this->vendor_name;
        $resolvedStatus = $this->status ?? ((bool) $this->is_active ? 'active' : 'inactive');

        return [
            'id' => $this->id,
            'name' => $resolvedName,
            'code' => $resolvedCode,
            'host' => $resolvedHost,
            'brand' => $resolvedBrand,
            'model' => $this->model,
            'pon_ports' => $this->pon_ports,
            'location_id' => $this->network_location_id,
            'router_id' => $this->router_id,
            'network_location_id' => $this->network_location_id,
            'status' => $resolvedStatus,
            'description' => $this->description,
            'odps_count' => $this->when(isset($this->odps_count), (int) $this->odps_count),
            'network_location' => $this->whenLoaded('networkLocation', function (): ?array {
                if ($this->networkLocation === null) {
                    return null;
                }

                return [
                    'id' => $this->networkLocation->id,
                    'name' => $this->networkLocation->name,
                    'code' => $this->networkLocation->code,
                    'status' => $this->networkLocation->status,
                ];
            }),

            // Backward-compatible payload for existing modules that still consume legacy OLT fields.
            'olt_code' => $this->olt_code ?? $resolvedCode,
            'olt_name' => $this->olt_name ?? $resolvedName,
            'mgmt_ip' => $this->mgmt_ip ?? $resolvedHost,
            'vendor_name' => $this->vendor_name ?? $resolvedBrand,
            'legacy_location_id' => $this->location_id,
            'location_name' => $this->location_name,
            'is_active' => $resolvedStatus === 'active',
            'onts_count' => $this->when(isset($this->onts_count), (int) $this->onts_count),
            'onts' => OntResource::collection($this->whenLoaded('onts')),
            'location' => $this->whenLoaded('location', function (): ?array {
                if ($this->location === null) {
                    return null;
                }

                return [
                    'id' => $this->location->id,
                    'location_code' => $this->location->location_code,
                    'location_name' => $this->location->location_name,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
