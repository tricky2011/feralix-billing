<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $hasActiveService = $this->input('has_active_service');

        $this->merge([
            'customer_type' => $this->filled('customer_type') ? strtolower((string) $this->input('customer_type')) : null,
            'status' => $this->filled('status') ? strtolower((string) $this->input('status')) : null,
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'sort_by' => $this->filled('sort_by') ? trim((string) $this->input('sort_by')) : 'full_name',
            'sort_direction' => $this->filled('sort_direction') ? strtolower(trim((string) $this->input('sort_direction'))) : 'asc',
            'has_active_service' => match (true) {
                $hasActiveService === 'true', $hasActiveService === '1', $hasActiveService === 1, $hasActiveService === true => true,
                $hasActiveService === 'false', $hasActiveService === '0', $hasActiveService === 0, $hasActiveService === false => false,
                default => $hasActiveService,
            },
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'customer_type' => ['nullable', Rule::enum(CustomerType::class)],
            'status' => ['nullable', Rule::enum(CustomerStatus::class)],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'olt_id' => ['nullable', 'integer', 'exists:olts,id'],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:technicians,id'],
            'has_active_service' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['full_name', 'customer_code', 'created_at', 'updated_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
