<?php

namespace Tests\Feature\Api;

use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_expose_router_password_in_api_responses(): void
    {
        $response = $this->postJson('/api/v1/admin/routers', [
            'router_code' => 'RTR-SEC-1',
            'router_name' => 'Router Secure 1',
            'router_role' => 'bng',
            'mgmt_ip' => '10.20.30.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'super-secret',
            'location_name' => 'HQ',
            'is_active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.has_api_username', true)
            ->assertJsonMissingPath('data.api_username')
            ->assertJsonPath('data.api_username_masked', 'ad****')
            ->assertJsonPath('data.has_api_password', true)
            ->assertJsonMissingPath('data.api_password');

        $router = Router::query()->firstOrFail();

        $this->assertNotSame('super-secret', $router->getRawOriginal('api_password'));
        $this->assertSame('super-secret', $router->api_password);
    }

    public function test_it_keeps_existing_password_when_router_is_updated_without_api_password(): void
    {
        $router = Router::query()->create([
            'router_code' => 'RTR-SEC-2',
            'router_name' => 'Router Secure 2',
            'router_role' => 'bng',
            'mgmt_ip' => '10.20.30.2',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'persisted-secret',
            'location_name' => 'HQ',
            'is_active' => true,
        ]);

        $encryptedPassword = $router->getRawOriginal('api_password');

        $response = $this->putJson("/api/v1/admin/routers/{$router->id}", [
            'router_code' => 'RTR-SEC-2',
            'router_name' => 'Router Secure 2 Updated',
            'router_role' => 'bng',
            'mgmt_ip' => '10.20.30.20',
            'api_port' => 8728,
            'api_username' => 'operator',
            'location_name' => 'Branch',
            'is_active' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.has_api_username', true)
            ->assertJsonMissingPath('data.api_username')
            ->assertJsonPath('data.api_username_masked', 'op******')
            ->assertJsonPath('data.has_api_password', true)
            ->assertJsonMissingPath('data.api_password');

        $router->refresh();

        $this->assertSame($encryptedPassword, $router->getRawOriginal('api_password'));
        $this->assertSame('persisted-secret', $router->api_password);
        $this->assertSame('Router Secure 2 Updated', $router->router_name);
        $this->assertSame('operator', $router->api_username);
        $this->assertFalse($router->is_active);
    }
}
