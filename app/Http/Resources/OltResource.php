<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OltResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'olt_code' => $this->olt_code,
            'olt_name' => $this->olt_name,
            'mgmt_ip' => $this->mgmt_ip,
            'vendor_name' => $this->vendor_name,
            'location_id' => $this->location_id,
            'location_name' => $this->location_name,
            'is_active' => $this->is_active,
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
