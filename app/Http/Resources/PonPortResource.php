<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PonPortResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'olt_id' => $this->olt_id,
            'port_number' => $this->port_number,
            'name' => $this->name,
            'max_capacity' => $this->max_capacity,
            'current_count' => $this->current_count,
            'sisa' => $this->sisa,
            'is_active' => $this->is_active,
            'is_full' => $this->is_full,
            'is_almost_full' => $this->is_almost_full,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}