<?php

namespace App\Http\Requests\Reseller;

use App\Enums\ResellerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ResellerPayloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    abstract protected function currentResellerId(): ?int;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reseller_code' => $this->filled('reseller_code') ? strtoupper(trim((string) $this->input('reseller_code'))) : null,
            'full_name' => $this->filled('full_name') ? trim((string) $this->input('full_name')) : null,
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'reseller_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('resellers', 'reseller_code')->ignore($this->currentResellerId()),
            ],
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::enum(ResellerStatus::class)],
        ];
    }
}
