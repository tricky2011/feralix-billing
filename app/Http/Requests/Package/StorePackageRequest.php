<?php

namespace App\Http\Requests\Package;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'package_name' => $this->filled('package_name') ? trim((string) $this->input('package_name')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'package_name' => ['required', 'string', 'max:150', 'unique:packages,package_name'],
            'monthly_price' => ['required', 'numeric', 'min:1'],
            'ip_pool_count' => ['nullable', 'integer', 'min:1', 'max:255'],
            'rate_limit_mbps' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
