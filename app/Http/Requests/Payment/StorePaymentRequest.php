<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\AdminPanelRequest;
use App\Models\Invoice;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_method' => $this->filled('payment_method') ? strtolower(trim((string) $this->input('payment_method'))) : null,
            'reference_no' => $this->filled('reference_no') ? trim((string) $this->input('reference_no')) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount_paid' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'string', 'max:50'],
            'paid_at' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('invoice_id')) {
                return;
            }

            $invoice = Invoice::query()
                ->select(['id', 'deleted_at'])
                ->find($this->integer('invoice_id'));

            if ($invoice === null) {
                return;
            }

            if ($invoice->deleted_at !== null) {
                $validator->errors()->add('invoice_id', 'Archived invoices cannot receive new payments.');
            }
        });
    }
}
