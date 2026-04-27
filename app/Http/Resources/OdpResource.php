<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OdpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'odc_id' => $this->odc_id,
            'olt_id' => $this->olt_id,
            'location_id' => $this->location_id,
            'name' => $this->name,
            'code' => $this->code,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'capacity' => $this->capacity,
            'used_ports' => (int) ($this->used_ports ?? 0),
            'status' => $this->status,
            'description' => $this->description,
            'location' => $this->whenLoaded('location', function (): ?array {
                if ($this->location === null) {
                    return null;
                }

                return [
                    'id' => $this->location->id,
                    'name' => $this->location->name,
                    'code' => $this->location->code,
                    'status' => $this->location->status,
                ];
            }),
            'odc' => $this->whenLoaded('odc', function (): ?array {
                if ($this->odc === null) {
                    return null;
                }

                return [
                    'id' => $this->odc->id,
                    'name' => $this->odc->name,
                    'code' => $this->odc->code,
                    'status' => $this->odc->status,
                ];
            }),
            'olt' => $this->whenLoaded('olt', function (): ?array {
                if ($this->olt === null) {
                    return null;
                }

                return [
                    'id' => $this->olt->id,
                    'name' => $this->olt->name ?? $this->olt->olt_name,
                    'code' => $this->olt->code ?? $this->olt->olt_code,
                    'status' => $this->olt->status,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
