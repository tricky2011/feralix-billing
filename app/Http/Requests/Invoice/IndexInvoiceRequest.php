<?php

namespace App\Http\Requests\Invoice;

use App\Enums\InvoicePaymentStatus;
use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class IndexInvoiceRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $paymentStatuses = $this->input('payment_statuses');

        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'billing_period' => $this->filled('billing_period') ? trim((string) $this->input('billing_period')) : null,
            'payment_status' => $this->filled('payment_status') ? strtolower(trim((string) $this->input('payment_status'))) : null,
            'payment_statuses' => is_array($paymentStatuses)
                ? array_values(array_filter(array_map(
                    static fn ($status) => is_string($status) ? strtolower(trim($status)) : null,
                    $paymentStatuses
                )))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'billing_period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'payment_status' => ['nullable', Rule::enum(InvoicePaymentStatus::class)],
            'payment_statuses' => ['nullable', 'array'],
            'payment_statuses.*' => [Rule::enum(InvoicePaymentStatus::class)],
            'is_overdue' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
