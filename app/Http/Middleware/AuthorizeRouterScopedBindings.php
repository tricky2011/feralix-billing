<?php

namespace App\Http\Middleware;

use App\Services\Access\RoleRouterScopeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeRouterScopedBindings
{
    public function __construct(private readonly RoleRouterScopeService $roleRouterScopeService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if ($route !== null) {
            $this->roleRouterScopeService->ensureRouteBindingsAccessible(
                $route->parametersWithoutNulls(),
                $request->user(),
            );
        }

        return $next($request);
    }
}
