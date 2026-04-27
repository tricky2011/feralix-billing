<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Odc\IndexOdcRequest;
use App\Http\Requests\Odc\StoreOdcRequest;
use App\Http\Requests\Odc\UpdateOdcRequest;
use App\Http\Resources\OdcResource;
use App\Models\Odc;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OdcController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(IndexOdcRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $odcs = Odc::query()
            ->with('location:id,name,code,status')
            ->withCount('odps')
            ->search($filters['search'] ?? null)
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $filters['location_id'] ?? null,
                fn ($query, $locationId) => $query->where('location_id', $locationId)
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            $odcs,
            OdcResource::class,
            'ODCs retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function store(StoreOdcRequest $request): JsonResponse
    {
        $odc = Odc::query()->create($request->validated());

        $this->activityLogger->record(
            $request->user(),
            'odc.created',
            'network',
            sprintf('Created ODC %s.', $odc->name),
            $request,
        );

        return $this->createdResponse(
            'ODC created successfully.',
            new OdcResource($odc->load(['location'])->loadCount('odps')),
        );
    }

    public function show(Odc $odc): JsonResponse
    {
        return $this->successResponse(
            'ODC retrieved successfully.',
            new OdcResource($odc->load(['location'])->loadCount('odps')),
        );
    }

    public function update(UpdateOdcRequest $request, Odc $odc): JsonResponse
    {
        $odc->update($request->validated());

        $this->activityLogger->record(
            $request->user(),
            'odc.updated',
            'network',
            sprintf('Updated ODC %s.', $odc->name),
            $request,
        );

        return $this->successResponse(
            'ODC updated successfully.',
            new OdcResource($odc->refresh()->load(['location'])->loadCount('odps')),
        );
    }

    public function destroy(Request $request, Odc $odc): JsonResponse
    {
        $odcName = $odc->name;
        $odc->delete();

        $this->activityLogger->record(
            $request->user(),
            'odc.deleted',
            'network',
            sprintf('Deleted ODC %s.', $odcName),
            $request,
        );

        return $this->successResponse('ODC deleted successfully.');
    }
}
