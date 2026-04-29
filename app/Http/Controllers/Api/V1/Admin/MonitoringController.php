<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MonitoringPppoeResource;
use App\Models\ServiceMonitorPppoeStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function pppoe(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'router_id' => 'nullable|integer|exists:routers,id',
            'status' => 'nullable|string|in:online,offline',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $query = ServiceMonitorPppoeStatus::query()
            ->with([
                'service:id,service_code,customer_id',
                'service.customer:id,full_name',
                'router:id,router_code,router_name',
            ])
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where(function ($inner) use ($search): void {
                    $inner
                        ->whereHas('service', fn ($sq) => $sq->where('service_code', 'like', "%{$search}%"))
                        ->orWhereHas('service.customer', fn ($sq) => $sq->where('full_name', 'like', "%{$search}%"))
                        ->orWhere('monitor_pppoe_username', 'like', "%{$search}%");
                })
            )
            ->when($filters['router_id'] ?? null, fn ($q, $routerId) => $q->where('router_id', $routerId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'online' THEN 0 ELSE 1 END")
            ->orderByDesc('last_seen_at');

        $summaryBase = ServiceMonitorPppoeStatus::query()
            ->when($filters['router_id'] ?? null, fn ($q, $routerId) => $q->where('router_id', $routerId));

        $onlineCount = (clone $summaryBase)->where('status', 'online')->count();
        $offlineCount = (clone $summaryBase)->where('status', 'offline')->count();

        $paginated = $query->paginate($perPage)->withQueryString();

        return $this->paginatedResponse(
            $paginated,
            MonitoringPppoeResource::class,
            'PPPoE monitoring retrieved.',
            [
                'filters' => $filters,
                'source' => 'mikrotik',
                'summary' => [
                    'online_count' => $onlineCount,
                    'offline_count' => $offlineCount,
                    'total' => $onlineCount + $offlineCount,
                ],
            ],
        );
    }
}
