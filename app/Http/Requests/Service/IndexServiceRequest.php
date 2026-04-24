<?php

namespace App\Http\Requests\Service;

use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class IndexServiceRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'billing_status' => $this->filled('billing_status') ? strtolower(trim((string) $this->input('billing_status'))) : null,
            'network_status' => $this->filled('network_status') ? strtolower(trim((string) $this->input('network_status'))) : null,
            'overall_status' => $this->filled('overall_status') ? strtolower(trim((string) $this->input('overall_status'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'billing_status' => ['nullable', Rule::enum(ServiceBillingStatus::class)],
            'network_status' => ['nullable', Rule::enum(ServiceNetworkStatus::class)],
            'overall_status' => ['nullable', Rule::enum(ServiceOverallStatus::class)],
            'with_trashed' => ['nullable', 'boolean'],
            'only_trashed' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
