<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TechnicianDashboard\ExportTechnicianDashboardRequest;
use App\Http\Requests\TechnicianDashboard\ShowTechnicianDashboardRequest;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Dashboard\TechnicianDashboardService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicianDashboardController extends Controller
{
    public function __construct(
        private readonly TechnicianDashboardService $technicianDashboardService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(ShowTechnicianDashboardRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $payload = $this->technicianDashboardService->build(
            $user,
            $request->validated(),
        );

        $this->activityLogger->record(
            $user,
            'technician_dashboard.viewed',
            'technician_dashboard',
            'Technician dashboard viewed.',
            $request,
        );

        return $this->successResponse(
            'Technician dashboard retrieved successfully.',
            $payload,
        );
    }

    public function exportPdf(ExportTechnicianDashboardRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $this->activityLogger->record(
            $user,
            'technician_dashboard.export_pdf_requested',
            'technician_dashboard',
            'Technician dashboard export PDF requested.',
            $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Export PDF belum tersedia.',
            'data' => null,
            'meta' => (object) [],
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
