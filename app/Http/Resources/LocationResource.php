<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_code' => $this->location_code,
            'location_name' => $this->location_name,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'customers_count' => $this->when(isset($this->customers_count), (int) $this->customers_count),
            'olts_count' => $this->when(isset($this->olts_count), (int) $this->olts_count),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
