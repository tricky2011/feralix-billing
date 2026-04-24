<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\AdminPanelRequest;
use App\Models\Service;
use Illuminate\Validation\Validator;

class GenerateMonthlyInvoiceRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'billing_period' => $this->filled('billing_period') ? trim((string) $this->input('billing_period')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'billing_period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'due_in_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
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
