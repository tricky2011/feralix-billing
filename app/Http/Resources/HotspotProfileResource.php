<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotspotProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profile_name' => $this->profile_name,
            'validity_mode' => $this->validity_mode?->value,
            'validity_days' => $this->validity_days,
            'data_limit_bytes' => $this->data_limit_bytes,
            'price' => $this->price,
            'selling_price' => $this->selling_price,
            'user_lock' => $this->user_lock?->value,
            'expired_mode' => $this->expired_mode?->value,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
