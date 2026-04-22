<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HotspotVoucher\ActivateHotspotVoucherRequest;
use App\Http\Requests\HotspotVoucher\IndexHotspotVoucherRequest;
use App\Http\Resources\HotspotVoucherResource;
use App\Models\HotspotVoucher;
use App\Services\Hotspot\HotspotVoucherService;
use Illuminate\Http\JsonResponse;

class HotspotVoucherController extends Controller
{
    public function __construct(private readonly HotspotVoucherService $hotspotVoucherService) {}

    public function index(IndexHotspotVoucherRequest $request)
    {
        $hotspotVouchers = $this->hotspotVoucherService->paginate($request->validated());

        return HotspotVoucherResource::collection($hotspotVouchers)->additional([
            'message' => 'Hotspot vouchers retrieved successfully.',
            'filters' => $request->validated(),
        ]);
    }

    public function show(HotspotVoucher $hotspotVoucher): JsonResponse
    {
        return response()->json([
            'message' => 'Hotspot voucher retrieved successfully.',
            'data' => new HotspotVoucherResource($this->hotspotVoucherService->find($hotspotVoucher)),
        ]);
    }

    public function activate(
        ActivateHotspotVoucherRequest $request,
        HotspotVoucher $hotspotVoucher
    ): JsonResponse {
        $hotspotVoucher = $this->hotspotVoucherService->activate($hotspotVoucher, $request->validated());

        return response()->json([
            'message' => 'Hotspot voucher activated successfully.',
            'data' => new HotspotVoucherResource($hotspotVoucher),
        ]);
    }
}
