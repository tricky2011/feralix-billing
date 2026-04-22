<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Router\IndexRouterRequest;
use App\Http\Requests\Router\StoreRouterRequest;
use App\Http\Requests\Router\UpdateRouterRequest;
use App\Http\Resources\RouterResource;
use App\Models\Router;
use Illuminate\Http\JsonResponse;

class RouterController extends Controller
{
    public function index(IndexRouterRequest $request)
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $routers = Router::query()
            ->withCount('scopes')
            ->search($filters['search'] ?? null)
            ->when(
                $filters['router_role'] ?? null,
                fn ($query, $routerRole) => $query->where('router_role', $routerRole)
            )
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy('router_name')
            ->paginate($perPage)
            ->withQueryString();

        return RouterResource::collection($routers)->additional([
            'message' => 'Routers retrieved successfully.',
            'filters' => $filters,
        ]);
    }

    public function store(StoreRouterRequest $request): JsonResponse
    {
        $router = Router::query()->create($request->validated());

        return response()->json([
            'message' => 'Router created successfully.',
            'data' => new RouterResource($router),
        ], 201);
    }

    public function show(Router $router): JsonResponse
    {
        return response()->json([
            'message' => 'Router retrieved successfully.',
            'data' => new RouterResource($router->load('scopes')),
        ]);
    }

    public function update(UpdateRouterRequest $request, Router $router): JsonResponse
    {
        $router->update($request->validated());

        return response()->json([
            'message' => 'Router updated successfully.',
            'data' => new RouterResource($router->refresh()->load('scopes')),
        ]);
    }

    public function destroy(Router $router): JsonResponse
    {
        $router->delete();

        return response()->json([
            'message' => 'Router deleted successfully.',
        ]);
    }
}
