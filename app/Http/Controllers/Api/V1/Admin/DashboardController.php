<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ShowAdminDashboardRequest;
use App\Http\Requests\Dashboard\SwitchDashboardRouterRequest;
use App\Models\User;
use App\Services\Dashboard\DashboardAnalyticsService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardAnalyticsService $dashboardAnalyticsService) {}

    public function index(ShowAdminDashboardRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        if ($user->isTechnician()) {
            return response()->json([
                'message' => 'Technician users must use the technician dashboard endpoint.',
                'data' => $this->dashboardAnalyticsService->technicianRedirectPayload(),
            ], 409);
        }

        return response()->json([
            'message' => 'Dashboard retrieved successfully.',
            'data' => $this->dashboardAnalyticsService->adminDashboard($user, $request->validated()),
        ]);
    }

    public function switchRouter(SwitchDashboardRouterRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return response()->json([
            'message' => 'Active dashboard router updated successfully.',
            'data' => $this->dashboardAnalyticsService->switchAdminDashboardRouter(
                $user,
                $request->validated()['router_id'] ?? null,
            ),
        ]);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException();
        }

        return $user;
    }
}
