<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VoucherBatch\IndexVoucherBatchRequest;
use App\Http\Requests\VoucherBatch\StoreVoucherBatchRequest;
use App\Http\Resources\VoucherBatchResource;
use App\Models\VoucherBatch;
use App\Services\Hotspot\VoucherBatchService;
use Illuminate\Http\JsonResponse;

class VoucherBatchController extends Controller
{
    public function __construct(private readonly VoucherBatchService $voucherBatchService) {}

    public function index(IndexVoucherBatchRequest $request)
    {
        $voucherBatches = $this->voucherBatchService->paginate($request->validated());

        return VoucherBatchResource::collection($voucherBatches)->additional([
            'message' => 'Voucher batches retrieved successfully.',
            'filters' => $request->validated(),
        ]);
    }

    public function store(StoreVoucherBatchRequest $request): JsonResponse
    {
        $voucherBatch = $this->voucherBatchService->generate($request->validated());

        return response()->json([
            'message' => 'Voucher batch generated successfully.',
            'data' => new VoucherBatchResource($voucherBatch),
        ], 201);
    }

    public function show(VoucherBatch $voucherBatch): JsonResponse
    {
        return response()->json([
            'message' => 'Voucher batch retrieved successfully.',
            'data' => new VoucherBatchResource($this->voucherBatchService->find($voucherBatch)),
        ]);
    }
}
