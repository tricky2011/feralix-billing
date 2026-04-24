<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vid\IndexVidRequest;
use App\Http\Requests\Vid\StoreVidRequest;
use App\Http\Requests\Vid\UpdateVidRequest;
use App\Http\Resources\VidResource;
use App\Models\Vid;
use App\Services\Inventory\VidService;
use Illuminate\Http\JsonResponse;

class VidController extends Controller
{
    public function __construct(private readonly VidService $vidService) {}

    public function index(IndexVidRequest $request)
    {
        $vids = $this->vidService->paginate($request->validated());

        return $this->paginatedResponse(
            $vids,
            VidResource::class,
            'VIDs retrieved successfully.',
            ['filters' => $request->validated()],
        );
    }

    public function store(StoreVidRequest $request): JsonResponse
    {
        $vid = $this->vidService->create($request->validated());

        return $this->createdResponse('VID created successfully.', new VidResource($vid));
    }

    public function show(Vid $vid): JsonResponse
    {
        return $this->successResponse('VID retrieved successfully.', new VidResource($vid->load([
            'router:id,router_code,router_name',
            'routerScope:id,router_id,scope_name',
            'customer:id,customer_code,full_name',
        ])));
    }

    public function update(UpdateVidRequest $request, Vid $vid): JsonResponse
    {
        $vid = $this->vidService->update($vid, $request->validated());

        return $this->successResponse('VID updated successfully.', new VidResource($vid));
    }

    public function destroy(Vid $vid): JsonResponse
    {
        $this->vidService->delete($vid);

        return $this->successResponse('VID deleted successfully.');
    }
}
