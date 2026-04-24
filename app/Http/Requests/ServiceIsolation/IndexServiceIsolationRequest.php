<?php

namespace App\Http\Requests\ServiceIsolation;

use App\Enums\ServiceIsolationStatus;
use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class IndexServiceIsolationRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null,
            'date_from' => $this->filled('date_from') ? trim((string) $this->input('date_from')) : null,
            'date_to' => $this->filled('date_to') ? trim((string) $this->input('date_to')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ServiceIsolationStatus::class)],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
