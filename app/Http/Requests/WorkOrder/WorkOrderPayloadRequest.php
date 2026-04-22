<?php

namespace App\Http\Requests\WorkOrder;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Ont;
use App\Models\Service;
use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class WorkOrderPayloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'wo_type' => $this->filled('wo_type') ? strtolower(trim((string) $this->input('wo_type'))) : null,
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null,
            'planned_subnet_cidr' => $this->filled('planned_subnet_cidr') ? trim((string) $this->input('planned_subnet_cidr')) : null,
            'work_notes' => $this->filled('work_notes') ? Str::of((string) $this->input('work_notes'))->trim()->value() : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'router_id' => ['required', 'integer', 'exists:routers,id'],
            'olt_id' => ['nullable', 'integer', 'exists:olts,id'],
            'ont_id' => ['nullable', 'integer', 'exists:onts,id'],
            'wo_type' => ['required', Rule::enum(WorkOrderType::class)],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:technicians,id'],
            'planned_monitor_vid' => ['nullable', 'integer', 'between:1,4094'],
            'planned_internet_vid' => ['nullable', 'integer', 'between:1,4094'],
            'planned_subnet_cidr' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::enum(WorkOrderStatus::class)],
            'work_notes' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateServiceCustomerConsistency($validator);
            $this->validateOntConsistency($validator);
            $this->validatePlannedSubnetCidr($validator);
            $this->validateAssignedTechnicianForExplicitStatuses($validator);
            $this->validateCompletedAtConsistency($validator);
            $this->validateScheduleWindow($validator);
        });
    }

    protected function currentWorkOrder(): ?WorkOrder
    {
        $workOrder = $this->route('workOrder') ?? $this->route('work_order');

        return $workOrder instanceof WorkOrder ? $workOrder : null;
    }

    private function validateServiceCustomerConsistency(Validator $validator): void
    {
        if (! $this->filled('service_id') || ! $this->filled('customer_id')) {
            return;
        }

        $service = Service::query()
            ->select(['id', 'customer_id'])
            ->find($this->integer('service_id'));

        if ($service !== null && (int) $service->customer_id !== $this->integer('customer_id')) {
            $validator->errors()->add('service_id', 'The selected service does not belong to the selected customer.');
        }
    }

    private function validateOntConsistency(Validator $validator): void
    {
        if (! $this->filled('ont_id')) {
            return;
        }

        $ont = Ont::query()
            ->select(['id', 'olt_id'])
            ->find($this->integer('ont_id'));

        if ($ont === null) {
            return;
        }

        if ($this->filled('olt_id') && (int) $ont->olt_id !== $this->integer('olt_id')) {
            $validator->errors()->add('ont_id', 'The selected ONT does not belong to the selected OLT.');
        }
    }

    private function validatePlannedSubnetCidr(Validator $validator): void
    {
        if (! $this->filled('planned_subnet_cidr')) {
            return;
        }

        $subnetCidr = (string) $this->input('planned_subnet_cidr');
        $segments = explode('/', $subnetCidr);

        if (count($segments) !== 2 || ! filter_var($segments[0], FILTER_VALIDATE_IP) || ! is_numeric($segments[1])) {
            $validator->errors()->add('planned_subnet_cidr', 'The planned subnet CIDR format is invalid.');

            return;
        }

        $networkIp = $segments[0];
        $prefix = (int) $segments[1];
        $isIpv4 = (bool) filter_var($networkIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $maxPrefix = $isIpv4 ? 32 : 128;

        if ($prefix < 0 || $prefix > $maxPrefix) {
            $validator->errors()->add('planned_subnet_cidr', 'The planned subnet CIDR prefix is out of range.');
        }
    }

    private function validateAssignedTechnicianForExplicitStatuses(Validator $validator): void
    {
        if (! $this->filled('status')) {
            return;
        }

        $status = (string) $this->input('status');
        $assignedTechnicianId = $this->exists('assigned_technician_id')
            ? ($this->input('assigned_technician_id') !== null ? (int) $this->input('assigned_technician_id') : null)
            : $this->currentWorkOrder()?->assigned_technician_id;

        if (
            in_array($status, [
                WorkOrderStatus::Assigned->value,
                WorkOrderStatus::InProgress->value,
                WorkOrderStatus::Completed->value,
            ], true)
            && $assignedTechnicianId === null
        ) {
            $validator->errors()->add(
                'assigned_technician_id',
                'An assigned technician is required when the work order status is assigned, in_progress, or completed.'
            );
        }
    }

    private function validateCompletedAtConsistency(Validator $validator): void
    {
        if (! $this->filled('completed_at')) {
            return;
        }

        if ((string) $this->input('status') !== WorkOrderStatus::Completed->value) {
            $validator->errors()->add('completed_at', 'The completed_at field can only be set when the work order status is completed.');
        }
    }

    private function validateScheduleWindow(Validator $validator): void
    {
        if (! $this->filled('scheduled_at') || ! $this->filled('completed_at')) {
            return;
        }

        if (strtotime((string) $this->input('scheduled_at')) > strtotime((string) $this->input('completed_at'))) {
            $validator->errors()->add('completed_at', 'The completed_at timestamp must be greater than or equal to scheduled_at.');
        }
    }
}
