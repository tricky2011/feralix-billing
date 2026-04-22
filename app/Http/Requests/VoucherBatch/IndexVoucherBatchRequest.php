<?php

namespace App\Http\Requests\VoucherBatch;

use Illuminate\Foundation\Http\FormRequest;

class IndexVoucherBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'reseller_id' => ['nullable', 'integer', 'exists:resellers,id'],
            'hotspot_profile_id' => ['nullable', 'integer', 'exists:hotspot_profiles,id'],
            'generated_from' => ['nullable', 'date'],
            'generated_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
