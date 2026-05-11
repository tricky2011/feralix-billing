<?php

namespace App\Http\Requests\HotspotVoucher;

use Illuminate\Foundation\Http\FormRequest;

class ActivateHotspotVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
