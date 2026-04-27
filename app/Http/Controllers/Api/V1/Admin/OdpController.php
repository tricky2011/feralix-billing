<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Odp\IndexOdpRequest;
use App\Http\Requests\Odp\StoreOdpRequest;
use App\Http\Requests\Odp\UpdateOdpRequest;
use App\Http\Resources\OdpResource;
use App\Models\Odp;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OdpController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(IndexOdpRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $odps = Odp::query()
            ->with([
                'location:id,name,code,status',
                'odc:id,name,code,status',
                'olt:id,name,code,olt_name,olt_code,status',
            ])
            ->search($filters['search'] ?? null)
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $filters['location_id'] ?? null,
                fn ($query, $locationId) => $query->where('location_id', $locationId)
            )
            ->when(
                $filters['odc_id'] ?? null,
                fn ($query, $odcId) => $query->where('odc_id', $odcId)
            )
            ->when(
                $filters['olt_id'] ?? null,
                fn ($query, $oltId) => $query->where('olt_id', $oltId)
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            $odps,
            OdpResource::class,
            'ODPs retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function store(StoreOdpRequest $request): JsonResponse
    {
        $odp = Odp::query()->create($request->validated());

        $this->activityLogger->record(
            $request->user(),
            'odp.created',
            'network',
            sprintf('Created ODP %s.', $odp->name),
            $request,
        );

        return $this->createdResponse(
            'ODP created successfully.',
            new OdpResource($odp->load(['location', 'odc', 'olt'])),
        );
    }

    public function show(Odp $odp): JsonResponse
    {
        return $this->successResponse(
            'ODP retrieved successfully.',
            new OdpResource($odp->load(['location', 'odc', 'olt'])),
        );
    }

    public function update(UpdateOdpRequest $request, Odp $odp): JsonResponse
    {
        $odp->update($request->validated());

        $this->activityLogger->record(
            $request->user(),
            'odp.updated',
            'network',
            sprintf('Updated ODP %s.', $odp->name),
            $request,
        );

        return $this->successResponse(
            'ODP updated successfully.',
            new OdpResource($odp->refresh()->load(['location', 'odc', 'olt'])),
        );
    }

    public function destroy(Request $request, Odp $odp): JsonResponse
    {
        $odpName = $odp->name;
        $odp->delete();

        $this->activityLogger->record(
            $request->user(),
            'odp.deleted',
            'network',
            sprintf('Deleted ODP %s.', $odpName),
            $request,
        );

        return $this->successResponse('ODP deleted successfully.');
    }
}
