<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HotspotProfile\IndexHotspotProfileRequest;
use App\Http\Requests\HotspotProfile\StoreHotspotProfileRequest;
use App\Http\Requests\HotspotProfile\UpdateHotspotProfileRequest;
use App\Http\Resources\HotspotProfileResource;
use App\Models\HotspotProfile;
use App\Services\Hotspot\HotspotProfileService;
use Illuminate\Http\JsonResponse;

class HotspotProfileController extends Controller
{
    public function __construct(private readonly HotspotProfileService $hotspotProfileService) {}

    public function index(IndexHotspotProfileRequest $request)
    {
        $profiles = $this->hotspotProfileService->paginate($request->validated());

        return HotspotProfileResource::collection($profiles)->additional([
            'message' => 'Hotspot profiles retrieved successfully.',
            'filters' => $request->validated(),
        ]);
    }

    public function store(StoreHotspotProfileRequest $request): JsonResponse
    {
        $profile = $this->hotspotProfileService->create($request->validated());

        return response()->json([
            'message' => 'Hotspot profile created successfully.',
            'data' => new HotspotProfileResource($profile),
        ], 201);
    }

    public function show(HotspotProfile $hotspotProfile): JsonResponse
    {
        return response()->json([
            'message' => 'Hotspot profile retrieved successfully.',
            'data' => new HotspotProfileResource($hotspotProfile),
        ]);
    }

    public function update(UpdateHotspotProfileRequest $request, HotspotProfile $hotspotProfile): JsonResponse
    {
        $hotspotProfile = $this->hotspotProfileService->update($hotspotProfile, $request->validated());

        return response()->json([
            'message' => 'Hotspot profile updated successfully.',
            'data' => new HotspotProfileResource($hotspotProfile),
        ]);
    }
}
