<?php

namespace App\Http\Requests\Olt;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreOltRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isActive = $this->input('is_active');

        $this->merge([
            'olt_code' => $this->filled('olt_code') ? Str::upper(trim((string) $this->input('olt_code'))) : null,
            'olt_name' => $this->filled('olt_name') ? trim((string) $this->input('olt_name')) : null,
            'mgmt_ip' => $this->filled('mgmt_ip') ? trim((string) $this->input('mgmt_ip')) : null,
            'vendor_name' => $this->filled('vendor_name') ? trim((string) $this->input('vendor_name')) : null,
            'location_name' => $this->filled('location_name') ? trim((string) $this->input('location_name')) : null,
            'is_active' => match (true) {
                $isActive === 'true', $isActive === '1', $isActive === 1, $isActive === true => true,
                $isActive === 'false', $isActive === '0', $isActive === 0, $isActive === false => false,
                default => $isActive,
            },
        ]);
    }

    public function rules(): array
    {
        return [
            'olt_code' => ['required', 'string', 'max:30', 'unique:olts,olt_code'],
            'olt_name' => ['required', 'string', 'max:150'],
            'mgmt_ip' => ['nullable', 'ip'],
            'vendor_name' => ['nullable', 'string', 'max:100'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'location_name' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
