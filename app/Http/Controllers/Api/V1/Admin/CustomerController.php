<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\BulkDeleteCustomerRequest;
use App\Http\Requests\Customer\BulkDisableCustomerRequest;
use App\Http\Requests\Customer\BulkGenerateInvoiceRequest;
use App\Http\Requests\Customer\IndexCustomerRequest;
use App\Http\Requests\Customer\PreviewCustomerProvisioningRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\StoreCustomerOnboardingRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\Customer;
use App\Services\Customer\CustomerBulkActionService;
use App\Services\Customer\CustomerOnboardingService;
use App\Services\Customer\CustomerProvisioningPreviewService;
use App\Services\MasterData\CustomerService;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly CustomerOnboardingService $customerOnboardingService,
        private readonly CustomerBulkActionService $customerBulkActionService,
        private readonly CustomerProvisioningPreviewService $customerProvisioningPreviewService,
    ) {}

    public function index(IndexCustomerRequest $request)
    {
        $customers = $this->customerService->paginate($request->validated());

        return CustomerResource::collection($customers)->additional([
            'message' => 'Customers retrieved successfully.',
            'filters' => $request->validated(),
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->create($request->validated());

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => new CustomerResource($customer),
        ], 201);
    }

    public function onboard(StoreCustomerOnboardingRequest $request): JsonResponse
    {
        $result = $this->customerOnboardingService->onboard($request->validated());

        return response()->json([
            'message' => 'Customer onboarded successfully.',
            'data' => [
                'customer' => new CustomerResource($result['customer']),
                'service' => new ServiceResource($result['service']),
                'work_order' => $result['work_order'] === null ? null : new WorkOrderResource($result['work_order']),
                'initial_invoice' => $result['invoice'] === null ? null : new InvoiceResource($result['invoice']),
            ],
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json([
            'message' => 'Customer retrieved successfully.',
            'data' => new CustomerResource($this->customerService->find($customer)),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customerService->update($customer, $request->validated());

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data' => new CustomerResource($customer),
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->customerService->delete($customer);

        return response()->json([
            'message' => 'Customer deleted successfully.',
        ]);
    }

    public function bulkDelete(BulkDeleteCustomerRequest $request): JsonResponse
    {
        $payload = $request->validated();

        return response()->json([
            'message' => 'Bulk delete processed successfully.',
            'data' => $this->customerBulkActionService->bulkDelete($payload['customer_ids']),
        ]);
    }

    public function bulkDisable(BulkDisableCustomerRequest $request): JsonResponse
    {
        $payload = $request->validated();

        return response()->json([
            'message' => 'Bulk disable processed successfully.',
            'data' => $this->customerBulkActionService->bulkDisable($payload['customer_ids']),
        ]);
    }

    public function bulkGenerateInvoice(BulkGenerateInvoiceRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Bulk invoice generation processed successfully.',
            'data' => $this->customerBulkActionService->bulkGenerateInvoice($request->validated()),
        ]);
    }

    public function provisioningPreview(PreviewCustomerProvisioningRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Provisioning preview generated successfully.',
            'data' => $this->customerProvisioningPreviewService->preview($request->validated()),
        ]);
    }
}
