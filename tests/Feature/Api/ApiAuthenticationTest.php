<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipApiAuthentication = true;

    public function test_guest_cannot_access_admin_or_technician_routes(): void
    {
        $this->getJson('/api/v1/admin/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('meta.code', 'api_authentication_required');

        $this->getJson('/api/v1/technician/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('meta.code', 'api_authentication_required');
    }

    public function test_api_login_returns_sanctum_token_and_authenticated_user_payload(): void
    {
        $user = User::factory()->admin()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'password',
            'device_name' => 'postman-local',
        ]);

        $token = $loginResponse->json('data.access_token');

        $loginResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.username', 'admin')
            ->assertJsonPath('data.user.role', UserRole::Admin->value)
            ->assertJsonPath('data.abilities.0', 'panel:admin');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', UserRole::Admin->value);
    }

    public function test_api_login_rejects_unknown_username(): void
    {
        User::factory()->admin()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'missing-admin',
            'password' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('username');
    }

    public function test_api_login_rejects_wrong_password(): void
    {
        User::factory()->admin()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'salah-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('username');
    }

    public function test_api_login_still_accepts_email_for_backward_compatibility(): void
    {
        $user = User::factory()->admin()->create([
            'username' => 'legacy-admin',
            'email' => 'legacy-admin@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'legacy-admin@example.com',
            'password' => 'password',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.username', 'legacy-admin');
    }

    public function test_authenticated_user_can_logout_and_revoke_current_token(): void
    {
        $user = User::factory()->superadmin()->create();
        $plainTextToken = $user->createToken('integration-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.revoked_tokens', 1);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'integration-test',
        ]);
    }
}
