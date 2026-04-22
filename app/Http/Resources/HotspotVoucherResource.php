<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotspotVoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'reseller_id' => $this->reseller_id,
            'hotspot_profile_id' => $this->hotspot_profile_id,
            'username' => $this->username,
            'password' => $this->password,
            'voucher_code' => $this->voucher_code,
            'locked_mac' => $this->locked_mac,
            'first_login_at' => $this->first_login_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'bytes_used' => $this->bytes_used,
            'status' => $this->status?->value,
            'batch' => $this->whenLoaded('batch', function (): array {
                return [
                    'id' => $this->batch->id,
                    'batch_code' => $this->batch->batch_code,
                    'generated_at' => $this->batch->generated_at?->toISOString(),
                ];
            }),
            'reseller' => $this->whenLoaded('reseller', function (): array {
                return [
                    'id' => $this->reseller->id,
                    'reseller_code' => $this->reseller->reseller_code,
                    'full_name' => $this->reseller->full_name,
                ];
            }),
            'hotspot_profile' => $this->whenLoaded('hotspotProfile', function (): array {
                return [
                    'id' => $this->hotspotProfile->id,
                    'profile_name' => $this->hotspotProfile->profile_name,
                    'validity_mode' => $this->hotspotProfile->validity_mode?->value,
                    'validity_days' => $this->hotspotProfile->validity_days,
                    'data_limit_bytes' => $this->hotspotProfile->data_limit_bytes,
                    'user_lock' => $this->hotspotProfile->user_lock?->value,
                    'expired_mode' => $this->hotspotProfile->expired_mode?->value,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
