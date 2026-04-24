<?php

namespace App\Http\Requests\WorkOrder;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class IndexWorkOrderRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'wo_type' => $this->filled('wo_type') ? strtolower(trim((string) $this->input('wo_type'))) : null,
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:technicians,id'],
            'wo_type' => ['nullable', Rule::enum(WorkOrderType::class)],
            'status' => ['nullable', Rule::enum(WorkOrderStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
