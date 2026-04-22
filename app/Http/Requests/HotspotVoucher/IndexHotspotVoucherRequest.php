<?php

namespace App\Http\Requests\HotspotVoucher;

use App\Enums\HotspotVoucherStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexHotspotVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'batch_id' => ['nullable', 'integer', 'exists:voucher_batches,id'],
            'reseller_id' => ['nullable', 'integer', 'exists:resellers,id'],
            'hotspot_profile_id' => ['nullable', 'integer', 'exists:hotspot_profiles,id'],
            'status' => ['nullable', Rule::enum(HotspotVoucherStatus::class)],
            'first_login_from' => ['nullable', 'date'],
            'first_login_to' => ['nullable', 'date'],
            'expires_from' => ['nullable', 'date'],
            'expires_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
