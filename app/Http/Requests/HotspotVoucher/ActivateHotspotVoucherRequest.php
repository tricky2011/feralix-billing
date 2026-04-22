<?php

namespace App\Http\Requests\HotspotVoucher;

use Illuminate\Foundation\Http\FormRequest;

class ActivateHotspotVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $macAddress = $this->filled('mac_address')
            ? strtoupper(str_replace('-', ':', trim((string) $this->input('mac_address'))))
            : null;

        $this->merge([
            'mac_address' => $macAddress,
        ]);
    }

    public function rules(): array
    {
        return [
            'mac_address' => ['required', 'regex:/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/'],
            'login_at' => ['nullable', 'date'],
        ];
    }
}
