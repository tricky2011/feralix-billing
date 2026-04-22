<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResellerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reseller_code' => $this->reseller_code,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'balance' => $this->balance,
            'status' => $this->status?->value,
            'voucher_batches_count' => $this->whenCounted('voucherBatches'),
            'hotspot_vouchers_count' => $this->whenCounted('hotspotVouchers'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
