<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\NetworkLocation\IndexNetworkLocationRequest;
use App\Http\Requests\NetworkLocation\StoreNetworkLocationRequest;
use App\Http\Requests\NetworkLocation\UpdateNetworkLocationRequest;
use App\Http\Resources\NetworkLocationResource;
use App\Models\NetworkLocation;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetworkLocationController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(IndexNetworkLocationRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 500));

        $locations = NetworkLocation::query()
            ->withCount(['olts', 'odcs', 'odps'])
            ->search($filters['search'] ?? null)
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            $locations,
            NetworkLocationResource::class,
            'Network locations retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function store(StoreNetworkLocationRequest $request): JsonResponse
    {
        $networkLocation = NetworkLocation::query()->create($request->validated());

        $this->activityLogger->record(
            $request->user(),
            'network_location.created',
            'network',
            sprintf('Created network location %s.', $networkLocation->name),
            $request,
        );

        return $this->createdResponse(
            'Network location created successfully.',
            new NetworkLocationResource($networkLocation),
        );
    }

    public function show(NetworkLocation $networkLocation): JsonResponse
    {
        return $this->successResponse(
            'Network location retrieved successfully.',
            new NetworkLocationResource($networkLocation->loadCount(['olts', 'odcs', 'odps'])),
        );
    }

    public function update(UpdateNetworkLocationRequest $request, NetworkLocation $networkLocation): JsonResponse
    {
        $networkLocation->update($request->validated());

        $this->activityLogger->record(
            $request->user(),
            'network_location.updated',
            'network',
            sprintf('Updated network location %s.', $networkLocation->name),
            $request,
        );

        return $this->successResponse(
            'Network location updated successfully.',
            new NetworkLocationResource($networkLocation->refresh()->loadCount(['olts', 'odcs', 'odps'])),
        );
    }

    public function destroy(Request $request, NetworkLocation $networkLocation): JsonResponse
    {
        $locationName = $networkLocation->name;
        $networkLocation->delete();

        $this->activityLogger->record(
            $request->user(),
            'network_location.deleted',
            'network',
            sprintf('Deleted network location %s.', $locationName),
            $request,
        );

        return $this->successResponse('Network location deleted successfully.');
    }
}
