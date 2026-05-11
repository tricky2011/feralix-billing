<?php

namespace App\Http\Requests\HotspotRouter;

use Illuminate\Foundation\Http\FormRequest;

class ActivateHotspotOnAllRoutersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voucher_id' => ['required', 'integer', 'exists:hotspot_vouchers,id'],
        ];
    }
}
