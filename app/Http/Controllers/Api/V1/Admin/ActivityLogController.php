<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityLog\IndexActivityLogRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    public function index(IndexActivityLogRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $query = ActivityLog::query()
            ->with('user:id,name,username,role')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%")
                        ->orWhere('ip', 'like', "%{$search}%");
                });
            })
            ->when($filters['user_id'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
            ->when($filters['module'] ?? null, fn ($query, string $module) => $query->where('module', $module))
            ->when($filters['created_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['created_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date));

        $logs = $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            $logs,
            ActivityLogResource::class,
            'Activity logs retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function show(ActivityLog $activityLog): JsonResponse
    {
        return $this->successResponse(
            'Activity log retrieved successfully.',
            new ActivityLogResource($activityLog->load('user:id,name,username,role')),
        );
    }
}
