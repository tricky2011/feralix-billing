<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class AutoSuspendInvoiceRequest extends AdminPanelRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'reference_date' => ['nullable', 'date'],
            'chunk' => ['nullable', 'integer', 'min:1', 'max:500'],
            'confirm_all' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $customerId = $this->input('customer_id');
            $serviceId = $this->input('service_id');
            $routerId = $this->input('router_id');
            $confirmAll = $this->boolean('confirm_all');

            $hasAnyFilter = $customerId !== null || $serviceId !== null || $routerId !== null;

            if (!$hasAnyFilter && !$confirmAll) {
                $validator->errors()->add(
                    'scope',
                    'At least one filter (customer_id, service_id, or router_id) must be provided, '
                    . 'or set confirm_all to true to apply to all overdue invoices.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'scope' => 'Auto-suspend requires a specific scope. Provide at least one filter '
                . '(customer_id, service_id, or router_id), or set confirm_all=true to execute on all overdue invoices.',
        ];
    }
}
