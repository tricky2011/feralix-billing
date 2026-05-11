<?php

namespace App\Http\Requests\HotspotRouter;

use Illuminate\Foundation\Http\FormRequest;

class ActivateHotspotOnRouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voucher_id' => ['required', 'integer', 'exists:hotspot_vouchers,id'],
            'router_id' => ['required', 'integer', 'exists:routers,id'],
        ];
    }
}
