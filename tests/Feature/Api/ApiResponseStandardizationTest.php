<?php

namespace Tests\Feature\Api;

use App\Models\Router;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiResponseStandardizationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipApiAuthentication = true;

    public function test_auth_login_uses_the_standard_success_envelope(): void
    {
        $user = User::factory()->admin()->create([
            'username' => 'api-standard',
            'email' => 'api-standard@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'api-standard',
            'password' => 'password',
            'device_name' => 'integration-check',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'API login successful.')
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertIsString($response->json('data.access_token'));
        $this->assertNotSame('', $response->json('data.access_token'));
    }

    public function test_paginated_admin_endpoint_uses_standard_success_and_meta_structure(): void
    {
        $this->authenticateApi();

        $router = Router::query()->create([
            'router_code' => 'RTR-API-001',
            'router_name' => 'Router API Standard',
            'router_role' => 'bng',
            'mgmt_ip' => '10.80.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'location_name' => 'API Site',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/admin/routers?search=API')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Routers retrieved successfully.')
            ->assertJsonPath('data.0.router_code', 'RTR-API-001')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('meta.filters.search', 'API');

        $this->getJson("/api/v1/admin/routers/{$router->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Router retrieved successfully.')
            ->assertJsonPath('data.id', $router->id)
            ->assertJsonPath('meta', []);
    }

    public function test_unauthenticated_error_uses_the_standard_error_envelope(): void
    {
        $this->getJson('/api/v1/admin/customers')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('meta.code', 'api_authentication_required');
    }

    public function test_forbidden_error_uses_the_standard_error_envelope(): void
    {
        $user = User::factory()->admin()->create();

        $this->authenticateApi($user);

        $this->patchJson('/api/v1/admin/dashboard/router-switch', [])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only superadmin can switch the active dashboard router.');
    }

    public function test_validation_not_found_and_server_errors_use_the_standard_error_envelope(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'username' => 'missing-user',
            'password' => 'wrong-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.username.0', 'The provided credentials are incorrect.');

        $this->authenticateApi();

        $this->getJson('/api/v1/admin/customers/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.');

        Route::middleware('api')->get('/api/v1/testing/boom-standardization', function (): never {
            throw new RuntimeException('boom');
        });

        $this->getJson('/api/v1/testing/boom-standardization')
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Internal server error.');
    }
}
