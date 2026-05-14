<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HotspotVoucher\ActivateHotspotVoucherRequest;
use App\Http\Requests\HotspotVoucher\IndexHotspotVoucherRequest;
use App\Http\Resources\HotspotVoucherResource;
use App\Models\HotspotVoucher;
use App\Services\Hotspot\HotspotVoucherActivationService;
use App\Services\Hotspot\HotspotVoucherService;
use Illuminate\Http\JsonResponse;

class HotspotVoucherController extends Controller
{
    public function __construct(
        private readonly HotspotVoucherService $hotspotVoucherService,
        private readonly HotspotVoucherActivationService $activationService,
    ) {}

    public function index(IndexHotspotVoucherRequest $request)
    {
        $hotspotVouchers = $this->hotspotVoucherService->paginate($request->validated());

        return $this->paginatedResponse(
            $hotspotVouchers,
            HotspotVoucherResource::class,
            'Hotspot vouchers retrieved successfully.',
            ['filters' => $request->validated()],
        );
    }

    public function show(HotspotVoucher $hotspotVoucher): JsonResponse
    {
        return $this->successResponse(
            'Hotspot voucher retrieved successfully.',
            new HotspotVoucherResource($this->hotspotVoucherService->find($hotspotVoucher)),
        );
    }

    public function activate(
        ActivateHotspotVoucherRequest $request,
        HotspotVoucher $hotspotVoucher
    ): JsonResponse {
        $this->activationService->activateVoucher($hotspotVoucher);

        return $this->successResponse(
            'Hotspot voucher activated successfully.',
            new HotspotVoucherResource($hotspotVoucher),
        );
    }

    public function deactivate(HotspotVoucher $hotspotVoucher): JsonResponse
    {
        $this->activationService->deactivateVoucher($hotspotVoucher);

        return $this->successResponse(
            'Hotspot voucher deactivated successfully.',
            new HotspotVoucherResource($hotspotVoucher),
        );
    }
}
