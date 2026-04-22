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
        return response()->json([
            'message' => 'Customer references retrieved successfully.',
            'data' => $this->customerReferenceService->get($request->validated()),
        ]);
    }
}
