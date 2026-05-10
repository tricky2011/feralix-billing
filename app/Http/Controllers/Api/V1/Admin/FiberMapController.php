<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\NetworkLocation;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Olt;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiberMapController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): JsonResponse
    {
        $routerId = $request->integer('router_id') ?: null;

        $locationCount = NetworkLocation::query()
            ->when($routerId, fn ($q, $id) => $q->where('router_id', $id))
            ->count();

        $oltCount = Olt::query()
            ->when($routerId, fn ($q, $id) => $q->where('router_id', $id))
            ->count();

        $odcCount = Odc::query()
            ->when(
                $routerId,
                fn ($q, $id) => $q->whereHas('location', fn ($lq) => $lq->where('router_id', $id))
            )
            ->count();

        $odpCount = Odp::query()
            ->when(
                $routerId,
                fn ($q, $id) => $q->whereHas('olt', fn ($oq) => $oq->where('router_id', $id))
            )
            ->count();

        $this->activityLogger->record(
            $request->user(),
            'fiber_map.viewed',
            'network',
            'Viewed fiber network map.',
            $request,
        );

        return $this->successResponse(
            'Fiber network map retrieved successfully.',
            [
                'summary' => [
                    'locations' => $locationCount,
                    'olts'      => $oltCount,
                    'odcs'      => $odcCount,
                    'odps'      => $odpCount,
                ],
                'nodes'       => [],
                'edges'       => [],
                'placeholder' => true,
            ],
        );
    }
}
