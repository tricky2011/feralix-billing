<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelegramBotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'router_id' => $this->router_id,
            'router' => new RouterResource($this->whenLoaded('router')),
            'bot_name' => $this->bot_name,
            'has_token' => $this->token !== null,
            'status' => $this->status,
            'groups_count' => $this->when(isset($this->groups_count), (int) $this->groups_count),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
