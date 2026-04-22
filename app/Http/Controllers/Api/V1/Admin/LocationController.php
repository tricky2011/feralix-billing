<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Location\IndexLocationRequest;
use App\Http\Requests\Location\StoreLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Services\MasterData\LocationService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function __construct(private readonly LocationService $locationService) {}

    public function index(IndexLocationRequest $request)
    {
        $locations = $this->locationService->paginate($request->validated());

        return LocationResource::collection($locations)->additional([
            'message' => 'Locations retrieved successfully.',
            'filters' => $request->validated(),
        ]);
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = $this->locationService->create($request->validated());

        return response()->json([
            'message' => 'Location created successfully.',
            'data' => new LocationResource($location),
        ], 201);
    }

    public function show(Location $location): JsonResponse
    {
        return response()->json([
            'message' => 'Location retrieved successfully.',
            'data' => new LocationResource($location->loadCount(['customers', 'olts'])),
        ]);
    }

    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        $location = $this->locationService->update($location, $request->validated());

        return response()->json([
            'message' => 'Location updated successfully.',
            'data' => new LocationResource($location),
        ]);
    }

    public function destroy(Location $location): JsonResponse
    {
        $this->locationService->delete($location);

        return response()->json([
            'message' => 'Location deleted successfully.',
        ]);
    }
}
