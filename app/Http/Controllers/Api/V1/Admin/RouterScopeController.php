<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RouterScope\IndexRouterScopeRequest;
use App\Http\Requests\RouterScope\StoreRouterScopeRequest;
use App\Http\Requests\RouterScope\UpdateRouterScopeRequest;
use App\Http\Resources\RouterScopeResource;
use App\Models\RouterScope;
use Illuminate\Http\JsonResponse;

class RouterScopeController extends Controller
{
    public function index(IndexRouterScopeRequest $request)
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $routerScopes = RouterScope::query()
            ->with('router:id,router_code,router_name')
            ->search($filters['search'] ?? null)
            ->when(
                $filters['router_id'] ?? null,
                fn ($query, $routerId) => $query->where('router_id', $routerId)
            )
            ->when(
                array_key_exists('is_special', $filters) && $filters['is_special'] !== null,
                fn ($query) => $query->where('is_special', (bool) $filters['is_special'])
            )
            ->orderBy('router_id')
            ->orderBy('scope_name')
            ->paginate($perPage)
            ->withQueryString();

        return RouterScopeResource::collection($routerScopes)->additional([
            'message' => 'Router scopes retrieved successfully.',
            'filters' => $filters,
        ]);
    }

    public function store(StoreRouterScopeRequest $request): JsonResponse
    {
        $routerScope = RouterScope::query()->create($request->validated());

        return response()->json([
            'message' => 'Router scope created successfully.',
            'data' => new RouterScopeResource($routerScope->load('router:id,router_code,router_name')),
        ], 201);
    }

    public function show(RouterScope $routerScope): JsonResponse
    {
        return response()->json([
            'message' => 'Router scope retrieved successfully.',
            'data' => new RouterScopeResource($routerScope->load('router:id,router_code,router_name')),
        ]);
    }

    public function update(UpdateRouterScopeRequest $request, RouterScope $routerScope): JsonResponse
    {
        $routerScope->update($request->validated());

        return response()->json([
            'message' => 'Router scope updated successfully.',
            'data' => new RouterScopeResource($routerScope->refresh()->load('router:id,router_code,router_name')),
        ]);
    }

    public function destroy(RouterScope $routerScope): JsonResponse
    {
        $routerScope->delete();

        return response()->json([
            'message' => 'Router scope deleted successfully.',
        ]);
    }
}
