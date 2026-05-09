<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ServiceAccessMode;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\BulkDeleteCustomerRequest;
use App\Http\Requests\Customer\BulkDisableCustomerRequest;
use App\Http\Requests\Customer\BulkGenerateInvoiceRequest;
use App\Http\Requests\Customer\IndexCustomerRequest;
use App\Http\Requests\Customer\PreviewCustomerProvisioningRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\StoreCustomerOnboardingRequest;
use App\Http\Requests\Customer\StoreCustomerProvisioningRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\Customer;
use App\Models\Router;
use App\Services\Customer\CustomerBulkActionService;
use App\Services\Customer\CustomerOnboardingService;
use App\Services\Customer\CustomerProvisioningPreviewService;
use App\Services\Customer\CustomerTerminationService;
use App\Services\Mikrotik\IpPoolSyncService;
use App\Services\Mikrotik\MikrotikPppoeSecretService;
use App\Services\MasterData\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly CustomerOnboardingService $customerOnboardingService,
        private readonly CustomerBulkActionService $customerBulkActionService,
        private readonly CustomerProvisioningPreviewService $customerProvisioningPreviewService,
        private readonly CustomerTerminationService $terminationService,
        private readonly MikrotikApiClientFactory $mikrotikClientFactory,
        private readonly IpPoolSyncService $ipPoolSyncService,
        private readonly MikrotikPppoeSecretService $pppoeSecretService,
    ) {}

    public function index(IndexCustomerRequest $request)
    {
        $customers = $this->customerService->paginate($request->validated());

        return $this->paginatedResponse(
            $customers,
            CustomerResource::class,
            'Customers retrieved successfully.',
            ['filters' => $request->validated()],
        );
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->create($request->validated());

        return $this->createdResponse('Customer created successfully.', new CustomerResource($customer));
    }

    public function onboard(StoreCustomerOnboardingRequest $request): JsonResponse
    {
        $result = $this->customerOnboardingService->onboard($request->validated());

        return $this->createdResponse('Customer onboarded successfully.', [
            'customer' => new CustomerResource($result['customer']),
            'service' => new ServiceResource($result['service']),
            'work_order' => $result['work_order'] === null ? null : new WorkOrderResource($result['work_order']),
            'initial_invoice' => $result['invoice'] === null ? null : new InvoiceResource($result['invoice']),
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        return $this->successResponse(
            'Customer retrieved successfully.',
            new CustomerResource($this->customerService->find($customer)),
        );
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customerService->update($customer, $request->validated());

        return $this->successResponse('Customer updated successfully.', new CustomerResource($customer));
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->customerService->delete($customer);

        return $this->successResponse('Customer deleted successfully.');
    }

    public function terminate(Customer $customer, Request $request): JsonResponse
    {
        try {
            $log = $this->terminationService->terminate($customer);

            return $this->successResponse('Customer terminated successfully.', [
                'log' => $log,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to terminate customer', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to terminate customer: ' . $e->getMessage(), status: 500);
        }
    }

    public function bulkDelete(BulkDeleteCustomerRequest $request): JsonResponse
    {
        $payload = $request->validated();

        return $this->successResponse(
            'Bulk delete processed successfully.',
            $this->customerBulkActionService->bulkDelete($payload['customer_ids']),
        );
    }

    public function bulkDisable(BulkDisableCustomerRequest $request): JsonResponse
    {
        $payload = $request->validated();

        return $this->successResponse(
            'Bulk disable processed successfully.',
            $this->customerBulkActionService->bulkDisable($payload['customer_ids']),
        );
    }

    public function bulkGenerateInvoice(BulkGenerateInvoiceRequest $request): JsonResponse
    {
        return $this->successResponse(
            'Bulk invoice generation processed successfully.',
            $this->customerBulkActionService->bulkGenerateInvoice($request->validated()),
        );
    }

    public function provisioningPreview(PreviewCustomerProvisioningRequest $request): JsonResponse
    {
        return $this->successResponse(
            'Provisioning preview generated successfully.',
            $this->customerProvisioningPreviewService->preview($request->validated()),
        );
    }

    public function provisioning(StoreCustomerProvisioningRequest $request): JsonResponse
    {
        $data = $request->validated();

        $customer = $this->customerService->create([
            'customer_code' => $data['customer_code'],
            'full_name' => $data['full_name'],
            'phone' => $data['phone'] ?? null,
            'contact' => $data['contact'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'preferred_olt_id' => $data['preferred_olt_id'] ?? null,
            'assigned_technician_id' => $data['assigned_technician_id'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'customer_type' => $data['customer_type'] ?? 'residential',
            'status' => $data['status'] ?? 'active',
            'notes' => 'Created via provisioning form. Install date: ' . ($data['install_date'] ?? 'N/A'),
        ]);

        $serviceData = [
            'customer_id' => $customer->id,
            'olt_id' => $data['preferred_olt_id'] ?? null,
            'router_id' => $data['router_id'] ?? null,
            'service_code' => 'SVC-' . strtoupper(Str::random(8)),
            'access_mode' => ServiceAccessMode::Pppoe->value,
            'pppoe_username' => $data['pppoe_username'] ?? null,
            'pppoe_password' => $data['pppoe_password'] ?? null,
            'pppoe_isolation_profile' => $data['pppoe_server'] ?? null,
            'monitor_vid' => $data['monitor_vid'] ?? null,
            'internet_vid' => $data['internet_vid'] ?? null,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'billing_status' => ServiceBillingStatus::Pending->value,
            'network_status' => ServiceNetworkStatus::Provisioning->value,
            'overall_status' => ServiceOverallStatus::Provisioning->value,
            'activation_date' => $data['install_date'] ?? null,
        ];

        $service = $customer->services()->create($serviceData);

        // 3B: Create PPPoE secret on Mikrotik
        if (! empty($data['router_id']) && ! empty($data['pppoe_username']) && ! empty($data['pppoe_password'])) {
            $router = Router::find($data['router_id']);
            if ($router) {
                $result = $this->pppoeSecretService->createSecret(
                    router: $router,
                    username: $data['pppoe_username'],
                    password: $data['pppoe_password'],
                    profile: 'default',
                    comment: 'Feralix: ' . ($data['full_name'] ?? '') . ' | VID: ' . ($data['internet_vid'] ?? '-'),
                    disabled: false,
                );

                if (! $result['success']) {
                    Log::warning('Failed to create PPPoE secret on Mikrotik', [
                        'customer' => $data['pppoe_username'] ?? null,
                        'router_id' => $data['router_id'] ?? null,
                        'error' => $result['message'],
                    ]);
                }
            }
        }

        // 3C: Sync IP pools to mark used IPs as reserved
        if (! empty($data['router_id'])) {
            try {
                $router = Router::find($data['router_id']);
                if ($router) {
                    $this->ipPoolSyncService->syncFromMikrotik($router);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to sync IP pools after provisioning', [
                    'router_id' => $data['router_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->createdResponse('Customer provisioned successfully.', [
            'customer' => new CustomerResource($customer),
            'service' => new ServiceResource($service),
        ]);
    }
}
