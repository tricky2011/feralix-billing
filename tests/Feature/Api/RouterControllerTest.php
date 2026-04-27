<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Router;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipApiAuthentication = true;

    public function test_admin_can_create_router(): void
    {
        $this->authenticateApi(User::factory()->admin()->create());

        $response = $this->postJson('/api/v1/admin/routers', [
            'name' => 'Router Core A',
            'host' => '10.10.10.1',
            'api_port' => 8728,
            'timeout' => 5,
            'use_ssl' => false,
            'status' => 'active',
            'api_username' => 'admin-core',
            'api_password' => 'super-secret',
            'acs_inform_url' => 'http://acs.local:7547',
            'acs_nbi_url' => 'http://acs.local:7557',
            'acs_username' => 'acs-user',
            'acs_password' => 'acs-secret',
            'description' => 'Router backbone utama',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Router Core A')
            ->assertJsonPath('data.host', '10.10.10.1')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.has_api_password', true)
            ->assertJsonPath('data.has_acs_password', true)
            ->assertJsonMissingPath('data.api_password')
            ->assertJsonMissingPath('data.acs_password');

        $this->assertDatabaseHas('routers', [
            'name' => 'Router Core A',
            'host' => '10.10.10.1',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_update_router(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter();
        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $response = $this->patchJson("/api/v1/admin/routers/{$router->id}", [
            'name' => 'Router Core A Updated',
            'host' => '10.10.10.20',
            'api_port' => 8729,
            'timeout' => 8,
            'use_ssl' => true,
            'status' => 'inactive',
            'api_username' => 'operator',
            'acs_inform_url' => 'http://acs.local:7547/inform',
            'acs_nbi_url' => 'http://acs.local:7557/nbi',
            'acs_username' => 'acs-operator',
            'description' => 'Router diperbarui',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Router Core A Updated')
            ->assertJsonPath('data.host', '10.10.10.20')
            ->assertJsonPath('data.api_port', 8729)
            ->assertJsonPath('data.timeout', 8)
            ->assertJsonPath('data.use_ssl', true)
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.api_username', 'operator');

        $this->assertDatabaseHas('routers', [
            'id' => $router->id,
            'name' => 'Router Core A Updated',
            'host' => '10.10.10.20',
            'status' => 'inactive',
        ]);
    }

    public function test_password_kosong_saat_update_tidak_overwrite_password_lama(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter([
            'api_password' => 'persisted-api-password',
            'acs_password' => 'persisted-acs-password',
        ]);
        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $rawApiPassword = $router->getRawOriginal('api_password');
        $rawAcsPassword = $router->getRawOriginal('acs_password');

        $this->patchJson("/api/v1/admin/routers/{$router->id}", [
            'name' => 'Router Tanpa Ganti Password',
            'api_password' => '',
            'acs_password' => null,
        ])->assertOk();

        $router->refresh();

        $this->assertSame($rawApiPassword, $router->getRawOriginal('api_password'));
        $this->assertSame($rawAcsPassword, $router->getRawOriginal('acs_password'));
        $this->assertSame('persisted-api-password', $router->api_password);
        $this->assertSame('persisted-acs-password', $router->acs_password);
    }

    public function test_list_router_tidak_expose_api_password_dan_acs_password(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter([
            'api_password' => 'api-secret',
            'acs_password' => 'acs-secret',
        ]);
        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $this->getJson('/api/v1/admin/routers')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.has_api_password', true)
            ->assertJsonPath('data.0.has_acs_password', true)
            ->assertJsonMissingPath('data.0.api_password')
            ->assertJsonMissingPath('data.0.acs_password');
    }

    public function test_show_router_tidak_expose_api_password_dan_acs_password(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter([
            'api_password' => 'api-secret',
            'acs_password' => 'acs-secret',
        ]);
        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $this->getJson("/api/v1/admin/routers/{$router->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_api_password', true)
            ->assertJsonPath('data.has_acs_password', true)
            ->assertJsonMissingPath('data.api_password')
            ->assertJsonMissingPath('data.acs_password');
    }

    public function test_test_connection_endpoint_return_envelope(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter([
            'host' => '127.0.0.1',
            'api_port' => 65530,
        ]);
        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $response = $this->postJson("/api/v1/admin/routers/{$router->id}/test-connection", []);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ]);
    }

    public function test_test_acs_endpoint_return_envelope_meskipun_genieacs_belum_terinstall(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter([
            'acs_nbi_url' => 'http://127.0.0.1:65531',
            'acs_username' => 'acs-admin',
            'acs_password' => 'acs-secret',
        ]);
        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $response = $this->postJson("/api/v1/admin/routers/{$router->id}/test-acs", []);

        $response
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ]);
    }

    public function test_unauthorized_user_ditolak(): void
    {
        $this->getJson('/api/v1/admin/routers')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_non_admin_ditolak(): void
    {
        $technician = User::factory()->technician()->create([
            'role' => UserRole::Technician->value,
        ]);

        $this->authenticateApi($technician);

        $this->getJson('/api/v1/admin/routers')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createRouter(array $overrides = []): Router
    {
        return Router::query()->create(array_merge([
            'name' => 'Router Core A',
            'host' => '10.10.10.1',
            'api_port' => 8728,
            'timeout' => 5,
            'use_ssl' => false,
            'status' => 'active',
            'api_username' => 'admin',
            'api_password' => 'secret',
            'acs_inform_url' => 'http://acs.local:7547',
            'acs_nbi_url' => null,
            'acs_username' => null,
            'acs_password' => null,
            'description' => 'Router test',
            'router_code' => 'RTR-CORE-A-'.strtoupper(substr(md5((string) microtime(true)), 0, 6)),
            'router_name' => 'Router Core A',
            'router_role' => 'core',
            'mgmt_ip' => '10.10.10.1',
            'location_name' => 'HQ',
            'is_active' => true,
        ], $overrides));
    }

    private function grantRouterAccess(User $user, Router $router): void
    {
        $user->accessibleRouters()->syncWithoutDetaching([$router->id]);
        $user->routers()->syncWithoutDetaching([$router->id]);
    }
}
