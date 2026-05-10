<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\NetworkLocation;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Olt;
use App\Services\Access\RoleRouterScopeService;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiberMapController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly RoleRouterScopeService $routerScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([]);

        $this->activityLogger->record(
            $request->user(),
            'fiber_map.viewed',
            'network',
            'Viewed fiber network map placeholder.',
            $request,
        );

        $routerIds = $this->routerScope->visibleRouterIds();

        $locationIds = null;
        $oltCount = 0;
        $odcCount = 0;
        $odpCount = 0;

        if ($routerIds !== null) {
            $locationIds = \App\Models\Olt::query()
                ->whereIn('router_id', $routerIds)
                ->pluck('network_location_id')
                ->filter()
                ->unique()
                ->values();

            $oltCount = Olt::query()->whereIn('router_id', $routerIds)->count();
            $odcCount = NetworkLocation::query()->whereIn('id', $locationIds)->withCount('odcs')->get()->sum('odcs_count');
            $odpCount = Olt::query()->whereIn('router_id', $routerIds)->withCount('odps')->get()->sum('odps_count');
            $locationIds = $locationIds->all();
        } else {
            $oltCount = Olt::query()->count();
            $odcCount = Odc::query()->count();
            $odpCount = Odp::query()->count();
        }

        return $this->successResponse(
            'Fiber network map placeholder retrieved successfully.',
            [
                'summary' => [
                    'locations' => NetworkLocation::query()->when($locationIds !== null, fn ($q) => $q->whereIn('id', $locationIds))->count(),
                    'olts' => $oltCount,
                    'odcs' => $odcCount,
                    'odps' => $odpCount,
                ],
                'nodes' => [],
                'edges' => [],
                'placeholder' => true,
            ],
        );
    }
}
