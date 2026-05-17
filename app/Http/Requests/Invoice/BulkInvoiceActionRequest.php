<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkInvoiceActionRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => $this->filled('action') ? strtolower(trim((string) $this->input('action'))) : null,
            'payment_method' => $this->filled('payment_method') ? strtolower(trim((string) $this->input('payment_method'))) : null,
            'reference_no' => $this->filled('reference_no') ? trim((string) $this->input('reference_no')) : null,
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'message' => $this->filled('message') ? trim((string) $this->input('message')) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);

        $this->replace(
            collect($this->all())
                ->except(['created_by'])
                ->toArray()
        );
    }

    public function rules(): array
    {
        return [
            'invoice_ids' => ['required', 'array', 'min:1', 'max:500'],
            'invoice_ids.*' => ['integer', 'distinct', 'exists:invoices,id'],
            'action' => ['required', Rule::in(['mark_overdue', 'mark_paid', 'delete', 'send_whatsapp'])],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'force' => ['nullable', 'boolean'],
            'reference_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('action') === 'mark_paid' && ! $this->filled('payment_method')) {
                $validator->errors()->add('payment_method', 'Payment method is required when marking invoices as paid.');
            }
        });
    }
}
