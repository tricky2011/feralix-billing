<?php

namespace App\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dashboard\DashboardAnalyticsService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardAnalyticsService $dashboardAnalyticsService) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException();
        }

        return response()->json([
            'message' => 'Technician dashboard retrieved successfully.',
            'data' => $this->dashboardAnalyticsService->technicianDashboard($user),
        ]);
    }
}
