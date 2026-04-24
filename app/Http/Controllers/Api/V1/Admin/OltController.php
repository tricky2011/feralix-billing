<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Olt\IndexOltRequest;
use App\Http\Requests\Olt\StoreOltRequest;
use App\Http\Requests\Olt\UpdateOltRequest;
use App\Http\Resources\OltResource;
use App\Models\Olt;
use Illuminate\Http\JsonResponse;

class OltController extends Controller
{
    public function index(IndexOltRequest $request)
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $olts = Olt::query()
            ->with('location:id,location_code,location_name')
            ->withCount('onts')
            ->search($filters['search'] ?? null)
            ->when(
                $filters['vendor_name'] ?? null,
                fn ($query, $vendorName) => $query->where('vendor_name', $vendorName)
            )
            ->when(
                $filters['location_id'] ?? null,
                fn ($query, $locationId) => $query->where('location_id', $locationId)
            )
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy('olt_name')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            $olts,
            OltResource::class,
            'OLTs retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function store(StoreOltRequest $request): JsonResponse
    {
        $olt = Olt::query()->create($request->validated());

        return $this->createdResponse('OLT created successfully.', new OltResource($olt->load('location')));
    }

    public function show(Olt $olt): JsonResponse
    {
        return $this->successResponse(
            'OLT retrieved successfully.',
            new OltResource($olt->load(['onts', 'location'])),
        );
    }

    public function update(UpdateOltRequest $request, Olt $olt): JsonResponse
    {
        $olt->update($request->validated());

        return $this->successResponse(
            'OLT updated successfully.',
            new OltResource($olt->refresh()->load(['onts', 'location'])),
        );
    }

    public function destroy(Olt $olt): JsonResponse
    {
        $olt->delete();

        return $this->successResponse('OLT deleted successfully.');
    }
}
