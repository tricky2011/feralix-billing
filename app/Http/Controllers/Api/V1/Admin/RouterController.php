<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Router\IndexRouterRequest;
use App\Http\Requests\Router\StoreRouterRequest;
use App\Http\Requests\Router\UpdateRouterRequest;
use App\Http\Resources\RouterResource;
use App\Models\Router;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Http\JsonResponse;

class RouterController extends Controller
{
    public function __construct(private readonly RoleRouterScopeService $roleRouterScopeService) {}

    public function index(IndexRouterRequest $request)
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $query = Router::query()
            ->withCount('scopes')
            ->search($filters['search'] ?? null)
            ->when(
                $filters['router_role'] ?? null,
                fn ($query, $routerRole) => $query->where('router_role', $routerRole)
            )
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', (bool) $filters['is_active'])
            );

        $this->roleRouterScopeService->applyRouterScope($query, 'id');

        $routers = $query
            ->orderBy('router_name')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            $routers,
            RouterResource::class,
            'Routers retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function store(StoreRouterRequest $request): JsonResponse
    {
        $router = Router::query()->create($request->validated());

        return $this->createdResponse('Router created successfully.', new RouterResource($router));
    }

    public function show(Router $router): JsonResponse
    {
        return $this->successResponse(
            'Router retrieved successfully.',
            new RouterResource($router->load('scopes')),
        );
    }

    public function update(UpdateRouterRequest $request, Router $router): JsonResponse
    {
        $router->update($request->validated());

        return $this->successResponse(
            'Router updated successfully.',
            new RouterResource($router->refresh()->load('scopes')),
        );
    }

    public function destroy(Router $router): JsonResponse
    {
        $router->delete();

        return $this->successResponse('Router deleted successfully.');
    }
}
