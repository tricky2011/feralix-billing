<?php

namespace App\Http\Requests\ServiceIsolation;

use App\Enums\ServiceIsolationType;
use App\Enums\ServiceIsolationTargetType;
use App\Models\Invoice;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceIsolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'isolation_type' => $this->filled('isolation_type') ? strtolower(trim((string) $this->input('isolation_type'))) : null,
            'target_type' => $this->filled('target_type') ? strtolower(trim((string) $this->input('target_type'))) : null,
            'address_list_name' => $this->filled('address_list_name') ? trim((string) $this->input('address_list_name')) : null,
            'target_subnet' => $this->filled('target_subnet') ? trim((string) $this->input('target_subnet')) : null,
            'target_identifier' => $this->filled('target_identifier') ? trim((string) $this->input('target_identifier')) : null,
            'redirect_url' => $this->filled('redirect_url') ? trim((string) $this->input('redirect_url')) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);

        $this->replace(
            collect($this->all())
                ->except(['created_by'])
                ->toArray()
        );
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'isolation_type' => ['nullable', Rule::enum(ServiceIsolationType::class)],
            'target_type' => ['nullable', Rule::enum(ServiceIsolationTargetType::class)],
            'address_list_name' => ['nullable', 'string', 'max:100'],
            'target_subnet' => ['nullable', 'string', 'max:50'],
            'target_identifier' => ['nullable', 'string', 'max:100'],
            'target_payload' => ['nullable', 'array'],
            'redirect_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('service_id')) {
                return;
            }

            $service = Service::query()
                ->select([
                    'id',
                    'router_id',
                    'subnet_cidr',
                    'vid_id',
                    'access_mode',
                    'pppoe_username',
                    'pppoe_isolation_profile',
                    'static_ip_address',
                    'static_mac_address',
                    'static_queue_name',
                    'address_list_name',
                    'isolation_method',
                ])
                ->with([
                    'vid:id,subnet_cidr',
                    'routerOperationStatus:id,service_id,pppoe_username,static_ip_address',
                ])
                ->find($this->integer('service_id'));

            if ($service === null) {
                return;
            }

            if ($this->filled('router_id') && (int) $service->router_id !== $this->integer('router_id')) {
                $validator->errors()->add('router_id', 'The selected router must match the router assigned to the selected service.');
            }

            $invoice = $this->resolveInvoice();

            if ($invoice !== null && (int) $invoice->service_id !== $service->id) {
                $validator->errors()->add('invoice_id', 'The selected invoice does not belong to the selected service.');
            }

            if ($this->input('isolation_type') === ServiceIsolationType::InvoiceOverdue->value && ! $this->filled('invoice_id')) {
                $validator->errors()->add('invoice_id', 'Invoice overdue isolation requires an invoice reference.');
            }

            if (
                $this->input('isolation_type') === ServiceIsolationType::InvoiceOverdue->value &&
                $invoice !== null &&
                ! $invoice->isOverdue()
            ) {
                $validator->errors()->add('invoice_id', 'Invoice overdue isolation can only be created for overdue invoices.');
            }

            $targetType = $this->filled('target_type')
                ? ServiceIsolationTargetType::from((string) $this->input('target_type'))
                : $service->resolvedIsolationTargetType();

            if ($targetType === ServiceIsolationTargetType::Subnet) {
                $effectiveSubnet = $this->filled('target_subnet')
                    ? (string) $this->input('target_subnet')
                    : $service->isolationTargetSubnet();

                if ($effectiveSubnet === null || $effectiveSubnet === '') {
                    $validator->errors()->add('target_subnet', 'The selected service does not have a subnet available for isolation.');

                    return;
                }

                $this->validateCidr($validator, $effectiveSubnet, 'target_subnet');

                return;
            }

            if (
                $targetType === ServiceIsolationTargetType::Pppoe &&
                ! $this->filled('target_identifier') &&
                $service->operationalPppoeUsername() === null &&
                blank($service->routerOperationStatus?->pppoe_username)
            ) {
                $validator->errors()->add('target_identifier', 'The selected service does not have a PPPoE username available for isolation.');
            }

            if (
                $targetType === ServiceIsolationTargetType::Static &&
                ! $this->filled('target_identifier') &&
                $service->operationalStaticIpAddress() === null &&
                blank($service->routerOperationStatus?->static_ip_address) &&
                $service->isolationTargetSubnet() === null
            ) {
                $validator->errors()->add('target_identifier', 'The selected service does not have a static binding target available for isolation.');
            }
        });
    }

    private function resolveInvoice(): ?Invoice
    {
        if (! $this->filled('invoice_id')) {
            return null;
        }

        return Invoice::query()
            ->select(['id', 'service_id', 'due_date', 'payment_status'])
            ->find($this->integer('invoice_id'));
    }

    private function validateCidr(Validator $validator, string $subnetCidr, string $field): void
    {
        $segments = explode('/', $subnetCidr);

        if (count($segments) !== 2 || ! filter_var($segments[0], FILTER_VALIDATE_IP) || ! is_numeric($segments[1])) {
            $validator->errors()->add($field, 'The subnet CIDR format is invalid.');

            return;
        }

        $networkIp = $segments[0];
        $prefix = (int) $segments[1];
        $isIpv4 = (bool) filter_var($networkIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $maxPrefix = $isIpv4 ? 32 : 128;

        if ($prefix < 0 || $prefix > $maxPrefix) {
            $validator->errors()->add($field, 'The subnet CIDR prefix is out of range.');
        }
    }
}
