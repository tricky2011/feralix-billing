<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Services\Access\RoleRouterScopeService;
use App\Services\Mikrotik\RouterStatsService;
use Illuminate\Http\JsonResponse;

class RouterStatsController extends Controller
{
    public function __construct(
        private readonly RouterStatsService $routerStatsService,
        private readonly RoleRouterScopeService $roleRouterScopeService,
    ) {}

    public function show(Router $router): JsonResponse
    {
        $this->roleRouterScopeService->ensureRouterAccessible($router->id);

        $result = $this->routerStatsService->getRouterStats($router);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'],
            'meta' => $result['meta'] ?? (object) [],
        ]);
    }
}
