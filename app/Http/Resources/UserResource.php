<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role?->value,
            'is_active' => (bool) $this->is_active,
            'technician_id' => $this->technician_id,
            'dashboard_active_router_id' => $this->dashboard_active_router_id,
            'router_ids' => $this->whenLoaded('accessibleRouters', fn () => $this->accessibleRouters
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all()),
            'routers' => RouterResource::collection($this->whenLoaded('accessibleRouters')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
