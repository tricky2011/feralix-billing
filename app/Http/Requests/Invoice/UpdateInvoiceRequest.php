<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\AdminPanelRequest;
use App\Models\Invoice;
use App\Models\Service;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvoiceRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'invoice_number' => $this->filled('invoice_number') ? Str::upper(trim((string) $this->input('invoice_number'))) : null,
            'billing_period' => $this->filled('billing_period') ? trim((string) $this->input('billing_period')) : null,
        ]);
    }

    public function rules(): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->route('invoice');

        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'invoice_number' => ['nullable', 'string', 'max:50', Rule::unique('invoices', 'invoice_number')->ignore($invoice->id)],
            'billing_period' => [
                'required',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                Rule::unique('invoices')->where(function ($query) use ($invoice): void {
                    $query
                        ->where('service_id', $this->input('service_id'))
                        ->where('billing_period', $this->input('billing_period'))
                        ->where('id', '!=', $invoice->id);
                }),
            ],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'issue_now' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('service_id') || ! $this->filled('customer_id')) {
                return;
            }

            $service = Service::query()
                ->select(['id', 'customer_id'])
                ->find($this->integer('service_id'));

            if ($service !== null && (int) $service->customer_id !== $this->integer('customer_id')) {
                $validator->errors()->add('service_id', 'The selected service does not belong to the selected customer.');
            }
        });
    }
}
