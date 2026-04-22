<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouterScopeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'router_id' => $this->router_id,
            'scope_name' => $this->scope_name,
            'monitor_vid' => $this->monitor_vid,
            'vid_start' => $this->vid_start,
            'vid_end' => $this->vid_end,
            'is_special' => $this->is_special,
            'notes' => $this->notes,
            'router' => $this->whenLoaded('router', function (): array {
                return [
                    'id' => $this->router->id,
                    'router_code' => $this->router->router_code,
                    'router_name' => $this->router->router_name,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
