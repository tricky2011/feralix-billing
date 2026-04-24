<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RouterScope\IndexRouterScopeRequest;
use App\Http\Requests\RouterScope\StoreRouterScopeRequest;
use App\Http\Requests\RouterScope\UpdateRouterScopeRequest;
use App\Http\Resources\RouterScopeResource;
use App\Models\RouterScope;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Http\JsonResponse;

class RouterScopeController extends Controller
{
    public function __construct(private readonly RoleRouterScopeService $roleRouterScopeService) {}

    public function index(IndexRouterScopeRequest $request)
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        if (($filters['router_id'] ?? null) !== null) {
            $this->roleRouterScopeService->ensureRouterAccessible((int) $filters['router_id']);
        }

        $query = RouterScope::query()
            ->with('router:id,router_code,router_name')
            ->search($filters['search'] ?? null)
            ->when(
                $filters['router_id'] ?? null,
                fn ($query, $routerId) => $query->where('router_id', $routerId)
            )
            ->when(
                array_key_exists('is_special', $filters) && $filters['is_special'] !== null,
                fn ($query) => $query->where('is_special', (bool) $filters['is_special'])
            );

        $this->roleRouterScopeService->applyRouterScope($query, 'router_id');

        $routerScopes = $query
            ->orderBy('router_id')
            ->orderBy('scope_name')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            $routerScopes,
            RouterScopeResource::class,
            'Router scopes retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function store(StoreRouterScopeRequest $request): JsonResponse
    {
        $this->roleRouterScopeService->ensureRouterAccessible((int) $request->validated()['router_id']);

        $routerScope = RouterScope::query()->create($request->validated());

        return $this->createdResponse(
            'Router scope created successfully.',
            new RouterScopeResource($routerScope->load('router:id,router_code,router_name')),
        );
    }

    public function show(RouterScope $routerScope): JsonResponse
    {
        return $this->successResponse(
            'Router scope retrieved successfully.',
            new RouterScopeResource($routerScope->load('router:id,router_code,router_name')),
        );
    }

    public function update(UpdateRouterScopeRequest $request, RouterScope $routerScope): JsonResponse
    {
        $this->roleRouterScopeService->ensureRouterAccessible((int) $request->validated()['router_id']);

        $routerScope->update($request->validated());

        return $this->successResponse(
            'Router scope updated successfully.',
            new RouterScopeResource($routerScope->refresh()->load('router:id,router_code,router_name')),
        );
    }

    public function destroy(RouterScope $routerScope): JsonResponse
    {
        $routerScope->delete();

        return $this->successResponse('Router scope deleted successfully.');
    }
}
