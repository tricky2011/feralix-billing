<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\Access\RoleRouterScopeService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelRole
{
    public function __construct(private readonly RoleRouterScopeService $roleRouterScopeService) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $resolvedRoles = collect($roles)
            ->map(static fn (string $role): ?UserRole => UserRole::tryFrom(trim($role)))
            ->filter()
            ->values()
            ->all();

        if (
            $resolvedRoles !== []
            && ! $this->roleRouterScopeService->allowsRoles($request->user(), ...$resolvedRoles)
        ) {
            throw new AuthorizationException('You are not allowed to access this endpoint.');
        }

        return $next($request);
    }
}
