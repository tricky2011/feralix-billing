<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ApiTokenAuthService
{
    /**
     * @return array{
     *     access_token:string,
     *     token_type:string,
     *     expires_at:string|null,
     *     abilities:list<string>,
     *     user:array<string, mixed>
     * }
     */
    public function login(array $payload, ?string $userAgent = null): array
    {
        $login = strtolower(trim((string) $payload['username']));

        $user = User::query()
            ->when(
                str_contains($login, '@'),
                fn ($query) => $query->where('email', $login),
                fn ($query) => $query->where('username', $login),
            )
            ->first();

        $passwordMatches = $user instanceof User
            && Hash::check((string) $payload['password'], (string) $user->password);

        if (! $user instanceof User || ! $passwordMatches) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['This user account is inactive.'],
            ]);
        }

        $tokenName = $this->resolveTokenName($payload['device_name'] ?? null, $userAgent);
        $abilities = $this->abilitiesFor($user);
        $expiresAt = $this->resolveExpiry();

        $user->tokens()
            ->where('name', $tokenName)
            ->delete();

        $token = $user->createToken($tokenName, $abilities, $expiresAt);

        return [
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
            'abilities' => $token->accessToken->abilities,
            'user' => $this->userPayload($user),
        ];
    }

    public function logoutCurrentToken(User $user): int
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken === null) {
            return 0;
        }

        return $currentToken->delete() ? 1 : 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function userPayload(User $user): array
    {
        $user->loadMissing([
            'technician:id,technician_code,full_name',
            'accessibleRouters:id',
        ]);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role?->value,
            'is_active' => (bool) $user->is_active,
            'technician_id' => $user->technician_id,
            'technician' => $user->technician !== null
                ? [
                    'id' => $user->technician->id,
                    'technician_code' => $user->technician->technician_code,
                    'full_name' => $user->technician->full_name,
                ]
                : null,
            'dashboard_active_router_id' => $user->dashboard_active_router_id,
            'accessible_router_ids' => $user->isSuperadmin()
                ? null
                : $user->accessibleRouters
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return list<string>
     */
    private function abilitiesFor(User $user): array
    {
        return match ($user->role) {
            UserRole::Superadmin => ['panel:admin', 'role:superadmin'],
            UserRole::Admin => ['panel:admin', 'role:admin'],
            UserRole::Technician => ['panel:technician', 'role:technician'],
            UserRole::Reseller => ['panel:reseller', 'role:reseller'],
        };
    }

    private function resolveTokenName(?string $deviceName, ?string $userAgent): string
    {
        $deviceName = trim((string) $deviceName);

        if ($deviceName !== '') {
            return $deviceName;
        }

        $userAgent = trim((string) $userAgent);

        if ($userAgent !== '') {
            return mb_substr($userAgent, 0, 100);
        }

        return 'api-token';
    }

    private function resolveExpiry(): ?Carbon
    {
        $expiration = config('sanctum.expiration');

        if (! is_numeric($expiration)) {
            return null;
        }

        $minutes = (int) $expiration;

        return $minutes > 0
            ? now()->addMinutes($minutes)
            : null;
    }
}
