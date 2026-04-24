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

        return $this->paginatedResponse(
            $payments,
            PaymentResource::class,
            'Payments retrieved successfully.',
            ['filters' => $request->validated()],
        );
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->create($request->validated());

        return $this->createdResponse('Payment recorded successfully.', new PaymentResource($payment));
    }

    public function show(Payment $payment): JsonResponse
    {
        return $this->successResponse(
            'Payment retrieved successfully.',
            new PaymentResource($this->paymentService->find($payment)),
        );
    }
}
