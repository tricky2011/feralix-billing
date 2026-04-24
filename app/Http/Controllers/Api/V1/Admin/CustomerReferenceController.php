<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\IndexCustomerReferenceRequest;
use App\Services\Customer\CustomerReferenceService;
use Illuminate\Http\JsonResponse;

class CustomerReferenceController extends Controller
{
    public function __construct(private readonly CustomerReferenceService $customerReferenceService) {}

    public function index(IndexCustomerReferenceRequest $request): JsonResponse
    {
        return $this->successResponse(
            'Customer references retrieved successfully.',
            $this->customerReferenceService->get($request->validated()),
        );
    }
}
