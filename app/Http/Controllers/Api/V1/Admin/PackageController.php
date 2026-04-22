<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Package\IndexPackageRequest;
use App\Http\Requests\Package\StorePackageRequest;
use App\Http\Requests\Package\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Services\MasterData\PackageService;
use Illuminate\Http\JsonResponse;

class PackageController extends Controller
{
    public function __construct(private readonly PackageService $packageService) {}

    public function index(IndexPackageRequest $request)
    {
        $packages = $this->packageService->paginate($request->validated());

        return PackageResource::collection($packages)->additional([
            'message' => 'Packages retrieved successfully.',
            'filters' => $request->validated(),
        ]);
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $package = $this->packageService->create($request->validated());

        return response()->json([
            'message' => 'Package created successfully.',
            'data' => new PackageResource($package),
        ], 201);
    }

    public function show(Package $package): JsonResponse
    {
        return response()->json([
            'message' => 'Package retrieved successfully.',
            'data' => new PackageResource($package),
        ]);
    }

    public function update(UpdatePackageRequest $request, Package $package): JsonResponse
    {
        $package = $this->packageService->update($package, $request->validated());

        return response()->json([
            'message' => 'Package updated successfully.',
            'data' => new PackageResource($package),
        ]);
    }

    public function destroy(Package $package): JsonResponse
    {
        $this->packageService->delete($package);

        return response()->json([
            'message' => 'Package deleted successfully.',
        ]);
    }
}
