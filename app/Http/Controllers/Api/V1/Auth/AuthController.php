<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ApiLoginRequest;
use App\Models\User;
use App\Services\Auth\ApiTokenAuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly ApiTokenAuthService $apiTokenAuthService) {}

    public function login(ApiLoginRequest $request): JsonResponse
    {
        return $this->createdResponse(
            'API login successful.',
            $this->apiTokenAuthService->login(
                $request->validated(),
                $request->userAgent(),
            ),
        );
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return $this->successResponse(
            'Authenticated user retrieved successfully.',
            $this->apiTokenAuthService->userPayload($user),
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $deletedTokens = $this->apiTokenAuthService->logoutCurrentToken($user);

        return $this->successResponse('API token revoked successfully.', [
            'revoked_tokens' => $deletedTokens,
        ]);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException();
        }

        return $user;
    }
}
