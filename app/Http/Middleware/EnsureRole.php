<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $resolvedRoles = collect($roles)
            ->map(static fn (string $role): ?UserRole => UserRole::tryFrom(trim($role)))
            ->filter()
            ->values()
            ->all();

        if ($resolvedRoles !== [] && ! in_array($user?->role, $resolvedRoles, true)) {
            throw new AuthorizationException('You are not allowed to access this endpoint.');
        }

        return $next($request);
    }
}
