<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\AdminPanelRequest;

class IndexPaymentRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'payment_method' => $this->filled('payment_method') ? strtolower(trim((string) $this->input('payment_method'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'paid_from' => ['nullable', 'date'],
            'paid_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
