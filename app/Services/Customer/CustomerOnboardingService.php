<?php

namespace App\Services\Customer;

use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\WorkOrderType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Vid;
use App\Models\WorkOrder;
use App\Services\Access\RoleRouterScopeService;
use App\Services\Billing\InvoiceService;
use App\Services\FieldWork\WorkOrderService;
use App\Services\MasterData\CustomerService;
use App\Services\Provisioning\FtthServiceManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerOnboardingService
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly FtthServiceManager $ftthServiceManager,
        private readonly WorkOrderService $workOrderService,
        private readonly InvoiceService $invoiceService,
        private readonly RoleRouterScopeService $roleRouterScopeService,
    ) {}

    public function onboard(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $customer = Customer::query()->create($this->customerPayload($payload));
            $service = $this->createInitialService($customer, $payload);

            if ($customer->preferred_olt_id === null && $service->olt_id !== null) {
                $customer->update([
                    'preferred_olt_id' => $service->olt_id,
                ]);
            }

            $workOrder = ($payload['create_work_order'] ?? true) === true
                ? $this->createOnboardingWorkOrder($customer, $service, $payload)
                : null;

            $invoice = ($payload['create_initial_invoice'] ?? false) === true
                ? $this->createInitialInvoice($customer, $service, $payload['initial_invoice'] ?? [])
                : null;

            return [
                'customer' => $this->customerService->find($customer->refresh()),
                'service' => $service,
                'work_order' => $workOrder,
                'invoice' => $invoice,
            ];
        });
    }

    private function createInitialService(Customer $customer, array $payload): Service
    {
        $servicePayload = $this->buildServicePayload($customer, $payload);

        return $this->ftthServiceManager->create($servicePayload);
    }

    private function createOnboardingWorkOrder(Customer $customer, Service $service, array $payload): WorkOrder
    {
        return $this->workOrderService->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'router_id' => $service->router_id,
            'olt_id' => $service->olt_id,
            'ont_id' => $service->ont_id,
            'wo_type' => WorkOrderType::NewInstall->value,
            'assigned_technician_id' => $customer->assigned_technician_id,
            'planned_monitor_vid' => $service->monitor_vid,
            'planned_internet_vid' => $service->internet_vid,
            'planned_subnet_cidr' => $service->subnet_cidr,
            'work_notes' => $payload['work_notes'] ?? sprintf('Onboarding customer %s.', $customer->customer_code),
            'scheduled_at' => $payload['scheduled_at'] ?? null,
        ]);
    }

    private function createInitialInvoice(Customer $customer, Service $service, array $payload): Invoice
    {
        $service->loadMissing('package:id,monthly_price');

        $invoiceDate = isset($payload['invoice_date'])
            ? Carbon::parse($payload['invoice_date'])->startOfDay()
            : now()->startOfDay();
        $dueDate = isset($payload['due_date'])
            ? Carbon::parse($payload['due_date'])->startOfDay()
            : $invoiceDate->copy()->addDays((int) ($payload['due_in_days'] ?? 10));

        return $this->invoiceService->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'billing_period' => $payload['billing_period'] ?? now()->format('Y-m'),
            'invoice_date' => $invoiceDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subtotal' => $service->package->monthly_price,
            'penalty_amount' => $payload['penalty_amount'] ?? 0,
        ]);
    }

    private function buildServicePayload(Customer $customer, array $payload): array
    {
        $servicePayload = $payload['service'];
        $vid = Vid::query()
            ->with('routerScope:id,router_id,monitor_vid')
            ->findOrFail((int) $servicePayload['vid_id']);

        $this->roleRouterScopeService->ensureModelAccessibleForInput($vid, 'service.vid_id');

        if (isset($servicePayload['router_id']) && $servicePayload['router_id'] !== null && (int) $servicePayload['router_id'] !== (int) $vid->router_id) {
            throw ValidationException::withMessages([
                'service.router_id' => 'The selected router must match the selected VID router.',
            ]);
        }

        $monitorVid = $servicePayload['monitor_vid'] ?? $vid->routerScope?->monitor_vid;

        if ($monitorVid === null) {
            throw ValidationException::withMessages([
                'service.monitor_vid' => 'The monitor VID is required and could not be inferred from the selected router scope.',
            ]);
        }

        $subnetCidr = $servicePayload['subnet_cidr'] ?? $vid->subnet_cidr;
        $gatewayIp = array_key_exists('gateway_ip', $servicePayload) ? $servicePayload['gateway_ip'] : $vid->gateway_ip;
        $dhcpPoolStart = $servicePayload['dhcp_pool_start'] ?? $vid->pool_start_ip;
        $dhcpPoolEnd = $servicePayload['dhcp_pool_end'] ?? $vid->pool_end_ip;

        $missingNetworkFields = collect([
            'subnet_cidr' => $subnetCidr,
            'dhcp_pool_start' => $dhcpPoolStart,
            'dhcp_pool_end' => $dhcpPoolEnd,
        ])->filter(static fn (mixed $value): bool => $value === null || $value === '');

        if ($missingNetworkFields->isNotEmpty()) {
            throw ValidationException::withMessages([
                'service.network' => [
                    sprintf(
                        'The selected VID does not provide a complete network allocation. Missing fields: %s.',
                        $missingNetworkFields->keys()->implode(', ')
                    ),
                ],
            ]);
        }

        $serviceCode = $servicePayload['service_code'] ?? $this->generateUniqueServiceCode($customer);
        $monitorPppoeUsername = $servicePayload['monitor_pppoe_username'] ?? $this->generateUniqueMonitorPppoeUsername($customer);
        $monitorPppoePassword = $servicePayload['monitor_pppoe_password'] ?? Str::random(20);

        return [
            'customer_id' => $customer->id,
            'package_id' => (int) $servicePayload['package_id'],
            'router_id' => $vid->router_id,
            'olt_id' => $servicePayload['olt_id'] ?? $payload['preferred_olt_id'] ?? $customer->preferred_olt_id,
            'ont_id' => $servicePayload['ont_id'] ?? null,
            'vid_id' => $vid->id,
            'service_code' => $serviceCode,
            'monitor_vid' => $monitorVid,
            'monitor_pppoe_username' => $monitorPppoeUsername,
            'monitor_pppoe_password' => $monitorPppoePassword,
            'internet_vid' => $servicePayload['internet_vid'] ?? $vid->vid,
            'subnet_cidr' => $subnetCidr,
            'gateway_ip' => $gatewayIp,
            'dhcp_pool_start' => $dhcpPoolStart,
            'dhcp_pool_end' => $dhcpPoolEnd,
            'ip_pool_count' => $servicePayload['ip_pool_count'] ?? null,
            'rate_limit_mbps' => $servicePayload['rate_limit_mbps'] ?? null,
            'isolation_method' => $servicePayload['isolation_method'] ?? ServiceIsolationMethod::AddressList->value,
            'address_list_name' => $servicePayload['address_list_name'] ?? $serviceCode,
            'billing_status' => $servicePayload['billing_status'] ?? ServiceBillingStatus::Pending->value,
            'network_status' => $servicePayload['network_status'] ?? ServiceNetworkStatus::Provisioning->value,
            'overall_status' => $servicePayload['overall_status'] ?? ServiceOverallStatus::Provisioning->value,
            'activation_date' => $servicePayload['activation_date'] ?? null,
            'notes' => $servicePayload['notes'] ?? null,
        ];
    }

    private function customerPayload(array $payload): array
    {
        return Arr::only($payload, [
            'customer_code',
            'full_name',
            'phone',
            'address',
            'location_id',
            'preferred_olt_id',
            'assigned_technician_id',
            'latitude',
            'longitude',
            'customer_type',
            'status',
        ]);
    }

    private function generateUniqueServiceCode(Customer $customer): string
    {
        $base = Str::upper('SVC-'.$customer->customer_code);
        $candidate = $base;
        $suffix = 1;

        while (Service::query()->where('service_code', $candidate)->exists()) {
            $candidate = sprintf('%s-%02d', $base, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    private function generateUniqueMonitorPppoeUsername(Customer $customer): string
    {
        $base = Str::lower(Str::slug('monitor-'.$customer->customer_code, '.'));
        $candidate = $base;
        $suffix = 1;

        while (Service::query()->where('monitor_pppoe_username', $candidate)->exists()) {
            $candidate = sprintf('%s.%02d', $base, $suffix);
            $suffix++;
        }

        return $candidate;
    }
}
