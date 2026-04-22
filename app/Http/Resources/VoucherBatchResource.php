<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reseller_id' => $this->reseller_id,
            'hotspot_profile_id' => $this->hotspot_profile_id,
            'batch_code' => $this->batch_code,
            'total_vouchers' => $this->total_vouchers,
            'total_cost' => $this->total_cost,
            'generated_at' => $this->generated_at?->toISOString(),
            'created_by' => $this->created_by,
            'vouchers_count' => $this->whenCounted('vouchers'),
            'reseller' => $this->whenLoaded('reseller', function (): array {
                return [
                    'id' => $this->reseller->id,
                    'reseller_code' => $this->reseller->reseller_code,
                    'full_name' => $this->reseller->full_name,
                    'phone' => $this->reseller->phone,
                    'balance' => $this->reseller->balance,
                    'status' => $this->reseller->status?->value,
                ];
            }),
            'hotspot_profile' => $this->whenLoaded('hotspotProfile', function (): array {
                return [
                    'id' => $this->hotspotProfile->id,
                    'profile_name' => $this->hotspotProfile->profile_name,
                    'validity_mode' => $this->hotspotProfile->validity_mode?->value,
                    'validity_days' => $this->hotspotProfile->validity_days,
                    'data_limit_bytes' => $this->hotspotProfile->data_limit_bytes,
                    'price' => $this->hotspotProfile->price,
                    'selling_price' => $this->hotspotProfile->selling_price,
                    'user_lock' => $this->hotspotProfile->user_lock?->value,
                    'expired_mode' => $this->hotspotProfile->expired_mode?->value,
                ];
            }),
            'creator' => $this->whenLoaded('creator', function (): ?array {
                if ($this->creator === null) {
                    return null;
                }

                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'vouchers' => HotspotVoucherResource::collection($this->whenLoaded('vouchers')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
