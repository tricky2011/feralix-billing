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

        return $this->paginatedResponse(
            $voucherBatches,
            VoucherBatchResource::class,
            'Voucher batches retrieved successfully.',
            ['filters' => $request->validated()],
        );
    }

    public function store(StoreVoucherBatchRequest $request): JsonResponse
    {
        $voucherBatch = $this->voucherBatchService->generate($request->validated());

        return $this->createdResponse('Voucher batch generated successfully.', new VoucherBatchResource($voucherBatch));
    }

    public function show(VoucherBatch $voucherBatch): JsonResponse
    {
        return $this->successResponse(
            'Voucher batch retrieved successfully.',
            new VoucherBatchResource($this->voucherBatchService->find($voucherBatch)),
        );
    }
}
