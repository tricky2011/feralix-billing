<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reseller\IndexResellerRequest;
use App\Http\Requests\Reseller\StoreResellerRequest;
use App\Http\Requests\Reseller\UpdateResellerRequest;
use App\Http\Resources\ResellerResource;
use App\Models\Reseller;
use App\Services\Hotspot\ResellerService;
use Illuminate\Http\JsonResponse;

class ResellerController extends Controller
{
    public function __construct(private readonly ResellerService $resellerService) {}

    public function index(IndexResellerRequest $request)
    {
        $resellers = $this->resellerService->paginate($request->validated());

        return $this->paginatedResponse(
            $resellers,
            ResellerResource::class,
            'Resellers retrieved successfully.',
            ['filters' => $request->validated()],
        );
    }

    public function store(StoreResellerRequest $request): JsonResponse
    {
        $reseller = $this->resellerService->create($request->validated());

        return $this->createdResponse('Reseller created successfully.', new ResellerResource($reseller));
    }

    public function show(Reseller $reseller): JsonResponse
    {
        return $this->successResponse(
            'Reseller retrieved successfully.',
            new ResellerResource($this->resellerService->find($reseller)),
        );
    }

    public function update(UpdateResellerRequest $request, Reseller $reseller): JsonResponse
    {
        $reseller = $this->resellerService->update($reseller, $request->validated());

        return $this->successResponse('Reseller updated successfully.', new ResellerResource($reseller));
    }
}
