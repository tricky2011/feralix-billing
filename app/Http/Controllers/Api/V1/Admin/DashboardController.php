<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ShowAdminDashboardRequest;
use App\Http\Requests\Dashboard\SwitchDashboardRouterRequest;
use App\Models\User;
use App\Services\Dashboard\DashboardAnalyticsService;
use App\Support\Api\ApiResponse;
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
            return ApiResponse::error(
                'Technician users must use the technician dashboard endpoint.',
                meta: $this->dashboardAnalyticsService->technicianRedirectPayload(),
                status: 409,
            );
        }

        return $this->successResponse(
            'Dashboard retrieved successfully.',
            $this->dashboardAnalyticsService->adminDashboard($user, $request->validated()),
        );
    }

    public function switchRouter(SwitchDashboardRouterRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return $this->successResponse(
            'Active dashboard router updated successfully.',
            $this->dashboardAnalyticsService->switchAdminDashboardRouter(
                $user,
                $request->validated()['router_id'] ?? null,
            ),
        );
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
