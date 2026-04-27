<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelegramGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'telegram_bot_id' => $this->telegram_bot_id,
            'bot' => new TelegramBotResource($this->whenLoaded('bot')),
            'router_id' => $this->router_id,
            'router' => new RouterResource($this->whenLoaded('router')),
            'group_name' => $this->group_name,
            'chat_id' => $this->chat_id,
            'group_type' => $this->group_type,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
