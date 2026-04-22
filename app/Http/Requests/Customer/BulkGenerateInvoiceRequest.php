<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkGenerateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'billing_period' => $this->filled('billing_period') ? trim((string) $this->input('billing_period')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_ids' => ['required', 'array', 'min:1', 'max:500'],
            'customer_ids.*' => ['integer', 'distinct', 'exists:customers,id'],
            'billing_period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'due_in_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('invoice_date') && $this->filled('due_date')) {
                if (strtotime((string) $this->input('due_date')) < strtotime((string) $this->input('invoice_date'))) {
                    $validator->errors()->add('due_date', 'The due date must be after or equal to the invoice date.');
                }
            }
        });
    }
}
