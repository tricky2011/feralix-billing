<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Models\Olt;
use App\Models\Ont;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $createInitialInvoice = $this->input('create_initial_invoice');
        $createWorkOrder = $this->input('create_work_order');

        $this->merge([
            'customer_code' => $this->filled('customer_code') ? Str::upper(trim((string) $this->input('customer_code'))) : null,
            'full_name' => $this->filled('full_name') ? trim((string) $this->input('full_name')) : null,
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'address' => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'customer_type' => $this->filled('customer_type') ? strtolower((string) $this->input('customer_type')) : null,
            'status' => $this->filled('status') ? strtolower((string) $this->input('status')) : CustomerStatus::Active->value,
            'work_notes' => $this->filled('work_notes') ? trim((string) $this->input('work_notes')) : null,
            'service' => is_array($this->input('service')) ? $this->normalizeServicePayload($this->input('service')) : null,
            'initial_invoice' => is_array($this->input('initial_invoice')) ? $this->normalizeInvoicePayload($this->input('initial_invoice')) : null,
            'create_initial_invoice' => match (true) {
                $createInitialInvoice === 'true', $createInitialInvoice === '1', $createInitialInvoice === 1, $createInitialInvoice === true => true,
                $createInitialInvoice === 'false', $createInitialInvoice === '0', $createInitialInvoice === 0, $createInitialInvoice === false => false,
                default => $createInitialInvoice,
            },
            'create_work_order' => match (true) {
                $createWorkOrder === null => true,
                $createWorkOrder === 'true', $createWorkOrder === '1', $createWorkOrder === 1, $createWorkOrder === true => true,
                $createWorkOrder === 'false', $createWorkOrder === '0', $createWorkOrder === 0, $createWorkOrder === false => false,
                default => $createWorkOrder,
            },
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_code' => ['required', 'string', 'max:30', 'unique:customers,customer_code'],
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'preferred_olt_id' => ['nullable', 'integer', 'exists:olts,id'],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:technicians,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'customer_type' => ['required', Rule::enum(CustomerType::class)],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
            'service' => ['required', 'array'],
            'service.package_id' => ['required', 'integer', 'exists:packages,id'],
            'service.router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'service.olt_id' => ['nullable', 'integer', 'exists:olts,id'],
            'service.ont_id' => ['nullable', 'integer', 'exists:onts,id'],
            'service.vid_id' => ['required', 'integer', 'exists:vids,id'],
            'service.service_code' => ['nullable', 'string', 'max:50'],
            'service.monitor_vid' => ['nullable', 'integer', 'between:1,4094'],
            'service.monitor_pppoe_username' => ['nullable', 'string', 'max:100'],
            'service.monitor_pppoe_password' => ['nullable', 'string', 'max:255'],
            'service.internet_vid' => ['nullable', 'integer', 'between:1,4094'],
            'service.subnet_cidr' => ['nullable', 'string', 'max:50'],
            'service.gateway_ip' => ['nullable', 'ip'],
            'service.dhcp_pool_start' => ['nullable', 'ip'],
            'service.dhcp_pool_end' => ['nullable', 'ip'],
            'service.ip_pool_count' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'service.rate_limit_mbps' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'service.isolation_method' => ['nullable', Rule::enum(ServiceIsolationMethod::class)],
            'service.address_list_name' => ['nullable', 'string', 'max:100'],
            'service.billing_status' => ['nullable', Rule::enum(ServiceBillingStatus::class)],
            'service.network_status' => ['nullable', Rule::enum(ServiceNetworkStatus::class)],
            'service.overall_status' => ['nullable', Rule::enum(ServiceOverallStatus::class)],
            'service.activation_date' => ['nullable', 'date'],
            'service.notes' => ['nullable', 'string'],
            'create_initial_invoice' => ['nullable', 'boolean'],
            'create_work_order' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date'],
            'work_notes' => ['nullable', 'string'],
            'initial_invoice' => ['nullable', 'array'],
            'initial_invoice.billing_period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'initial_invoice.invoice_date' => ['nullable', 'date'],
            'initial_invoice.due_date' => ['nullable', 'date'],
            'initial_invoice.due_in_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'initial_invoice.penalty_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validatePreferredOltMatchesLocation($validator);
            $this->validateServiceOntConsistency($validator);
            $this->validateInvoiceDates($validator);
        });
    }

    private function normalizeServicePayload(array $payload): array
    {
        return [
            ...$payload,
            'service_code' => isset($payload['service_code']) && $payload['service_code'] !== null
                ? Str::upper(trim((string) $payload['service_code']))
                : null,
            'monitor_pppoe_username' => isset($payload['monitor_pppoe_username']) && $payload['monitor_pppoe_username'] !== null
                ? strtolower(trim((string) $payload['monitor_pppoe_username']))
                : null,
            'monitor_pppoe_password' => array_key_exists('monitor_pppoe_password', $payload)
                ? (string) $payload['monitor_pppoe_password']
                : null,
            'subnet_cidr' => isset($payload['subnet_cidr']) && $payload['subnet_cidr'] !== null
                ? trim((string) $payload['subnet_cidr'])
                : null,
            'gateway_ip' => isset($payload['gateway_ip']) && $payload['gateway_ip'] !== null
                ? trim((string) $payload['gateway_ip'])
                : null,
            'dhcp_pool_start' => isset($payload['dhcp_pool_start']) && $payload['dhcp_pool_start'] !== null
                ? trim((string) $payload['dhcp_pool_start'])
                : null,
            'dhcp_pool_end' => isset($payload['dhcp_pool_end']) && $payload['dhcp_pool_end'] !== null
                ? trim((string) $payload['dhcp_pool_end'])
                : null,
            'isolation_method' => isset($payload['isolation_method']) && $payload['isolation_method'] !== null
                ? strtolower(trim((string) $payload['isolation_method']))
                : null,
            'address_list_name' => isset($payload['address_list_name']) && $payload['address_list_name'] !== null
                ? trim((string) $payload['address_list_name'])
                : null,
            'billing_status' => isset($payload['billing_status']) && $payload['billing_status'] !== null
                ? strtolower(trim((string) $payload['billing_status']))
                : null,
            'network_status' => isset($payload['network_status']) && $payload['network_status'] !== null
                ? strtolower(trim((string) $payload['network_status']))
                : null,
            'overall_status' => isset($payload['overall_status']) && $payload['overall_status'] !== null
                ? strtolower(trim((string) $payload['overall_status']))
                : null,
            'notes' => isset($payload['notes']) && $payload['notes'] !== null
                ? trim((string) $payload['notes'])
                : null,
        ];
    }

    private function normalizeInvoicePayload(array $payload): array
    {
        return [
            ...$payload,
            'billing_period' => isset($payload['billing_period']) && $payload['billing_period'] !== null
                ? trim((string) $payload['billing_period'])
                : null,
        ];
    }

    private function validatePreferredOltMatchesLocation(Validator $validator): void
    {
        if (! $this->filled('preferred_olt_id') || ! $this->filled('location_id')) {
            return;
        }

        $olt = Olt::query()
            ->select(['id', 'location_id'])
            ->find($this->integer('preferred_olt_id'));

        if ($olt !== null && $olt->location_id !== null && (int) $olt->location_id !== $this->integer('location_id')) {
            $validator->errors()->add('preferred_olt_id', 'The selected OLT does not belong to the selected location.');
        }
    }

    private function validateServiceOntConsistency(Validator $validator): void
    {
        if (! $this->filled('service.ont_id')) {
            return;
        }

        $ont = Ont::query()
            ->select(['id', 'olt_id'])
            ->find((int) $this->input('service.ont_id'));

        if ($ont === null) {
            return;
        }

        if ($this->filled('service.olt_id') && (int) $ont->olt_id !== (int) $this->input('service.olt_id')) {
            $validator->errors()->add('service.ont_id', 'The selected ONT does not belong to the selected OLT.');
        }
    }

    private function validateInvoiceDates(Validator $validator): void
    {
        if (! $this->boolean('create_initial_invoice')) {
            return;
        }

        $invoiceDate = data_get($this->input('initial_invoice'), 'invoice_date');
        $dueDate = data_get($this->input('initial_invoice'), 'due_date');

        if ($invoiceDate !== null && $dueDate !== null && strtotime((string) $dueDate) < strtotime((string) $invoiceDate)) {
            $validator->errors()->add('initial_invoice.due_date', 'The initial invoice due date must be after or equal to the invoice date.');
        }
    }
}
