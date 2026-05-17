<?php

use App\Http\Middleware\AuthorizeRouterScopedBindings;
use App\Http\Middleware\EnsurePanelRole;
use App\Http\Middleware\EnsureRole;
use App\Support\Api\ApiResponse;
use App\Support\DatabaseConnectionFailureDetector;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Middleware\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'panel.role' => EnsurePanelRole::class,
            'role' => EnsureRole::class,
            'router.scope.bindings' => AuthorizeRouterScopedBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return ApiResponse::error('Unauthenticated.', meta: [
                'code' => 'api_authentication_required',
            ], status: 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage() !== '' ? $exception->getMessage() : 'This action is unauthorized.',
                status: 403,
            );
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage() !== '' ? $exception->getMessage() : 'This action is unauthorized.',
                status: 403,
            );
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage() !== '' ? $exception->getMessage() : 'The given data was invalid.',
                errors: $exception->errors(),
                status: $exception->status,
            );
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return ApiResponse::error('Resource not found.', status: 404);
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return ApiResponse::error(
                'Too many requests. Please slow down.',
                meta: [
                    'code' => 'rate_limit_exceeded',
                    'retry_after' => $exception->getHeaders()['Retry-After'] ?? null,
                ],
                status: 429,
            );
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return ApiResponse::error(
                'Too many requests. Please slow down.',
                meta: [
                    'code' => 'rate_limit_exceeded',
                    'retry_after' => $exception->getHeaders()['Retry-After'] ?? null,
                ],
                status: 429,
            );
        });

        $exceptions->render(function (Throwable $throwable, Request $request) {
            $failureDetector = app(DatabaseConnectionFailureDetector::class);

            if (! $failureDetector->isConnectionFailure($throwable)) {
                return null;
            }

            $connectionName = $throwable instanceof QueryException
                ? $throwable->getConnectionName()
                : config('database.default');

            $payload = $failureDetector->describe($throwable, $connectionName);

            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::error(
                    (string) ($payload['message'] ?? 'Database unavailable.'),
                    meta: [
                        'code' => $payload['error'] ?? 'database_unavailable',
                        'connection' => $payload['connection'] ?? $connectionName,
                    ],
                    status: 503,
                );
            }

            return response()->view('database-unavailable', $payload, 503);
        });

        $exceptions->render(function (Throwable $throwable, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return ApiResponse::error('Internal server error.', status: 500);
        });
    })->create();
