<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\IndexPaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\Billing\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(IndexPaymentRequest $request)
    {
        $payments = $this->paymentService->paginate($request->validated());

        return PaymentResource::collection($payments)->additional([
            'message' => 'Payments retrieved successfully.',
            'filters' => $request->validated(),
        ]);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->create($request->validated());

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'data' => new PaymentResource($payment),
        ], 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json([
            'message' => 'Payment retrieved successfully.',
            'data' => new PaymentResource($this->paymentService->find($payment)),
        ]);
    }
}
