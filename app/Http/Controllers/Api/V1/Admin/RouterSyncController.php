<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RouterSync\RunRouterSyncRequest;
use App\Models\Router;
use App\Services\Access\RoleRouterScopeService;
use App\Services\Audit\ActivityLogger;
use App\Services\Network\RouterSyncService;
use Illuminate\Http\JsonResponse;

class RouterSyncController extends Controller
{
    public function __construct(
        private readonly RoleRouterScopeService $roleRouterScopeService,
        private readonly RouterSyncService $routerSyncService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function syncPppoe(RunRouterSyncRequest $request): JsonResponse
    {
        return $this->runOperation(
            $request,
            'router_sync.pppoe',
            'PPPoE',
            fn (Router $router): array => $this->routerSyncService->syncPppoe($router),
        );
    }

    public function syncStatic(RunRouterSyncRequest $request): JsonResponse
    {
        return $this->runOperation(
            $request,
            'router_sync.static',
            'Static',
            fn (Router $router): array => $this->routerSyncService->syncStatic($router),
        );
    }

    public function syncAddressList(RunRouterSyncRequest $request): JsonResponse
    {
        return $this->runOperation(
            $request,
            'router_sync.address_list',
            'Address List',
            fn (Router $router): array => $this->routerSyncService->syncAddressList($router),
        );
    }

    public function syncAll(RunRouterSyncRequest $request): JsonResponse
    {
        return $this->runOperation(
            $request,
            'router_sync.all',
            'All',
            fn (Router $router): array => $this->routerSyncService->syncAll($router),
        );
    }

    /**
     * @param  callable(Router): array{success:bool,message:string,data:array<string,mixed>,meta?:array<string,mixed>}  $operation
     */
    private function runOperation(
        RunRouterSyncRequest $request,
        string $action,
        string $operationLabel,
        callable $operation,
    ): JsonResponse {
        $payload = $request->validated();
        $routerId = (int) $payload['router_id'];

        $this->roleRouterScopeService->ensureRouterAccessible($routerId);

        $router = Router::query()
            ->where('is_active', true)
            ->findOrFail($routerId);

        $result = $operation($router);

        $this->activityLogger->record(
            $request->user(),
            $action,
            'router-sync',
            sprintf(
                'Router sync %s for %s (%s): %s.',
                strtolower($operationLabel),
                $router->router_name ?? ('#'.$router->id),
                $router->router_code ?? '-',
                ($result['success'] ?? false) ? 'success' : 'failed',
            ),
            $request,
        );

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? 'Operation finished.'),
            'data' => $result['data'] ?? (object) [],
            'meta' => $result['meta'] ?? (object) [],
        ]);
    }
}
