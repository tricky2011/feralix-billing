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
        $request->validate([]);

        $this->activityLogger->record(
            $request->user(),
            'fiber_map.viewed',
            'network',
            'Viewed fiber network map placeholder.',
            $request,
        );

        return $this->successResponse(
            'Fiber network map placeholder retrieved successfully.',
            [
                'summary' => [
                    'locations' => NetworkLocation::query()->count(),
                    'olts' => Olt::query()->count(),
                    'odcs' => Odc::query()->count(),
                    'odps' => Odp::query()->count(),
                ],
                'nodes' => [],
                'edges' => [],
                'placeholder' => true,
            ],
        );
    }
}
