<?php

namespace Tests\Feature\Api;

use App\Models\Router;
use App\Models\User;
use App\Services\Network\RouterSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class RouterSyncControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipApiAuthentication = true;

    public function test_admin_bisa_sync_pppoe(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter();

        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $this->mock(RouterSyncService::class, function (MockInterface $mock) use ($router): void {
            $mock->shouldReceive('syncPppoe')
                ->once()
                ->andReturn($this->mockResult('pppoe', $router));
        });

        $response = $this->postJson('/api/v1/admin/router-sync/pppoe', [
            'router_id' => $router->id,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Sync pppoe dijalankan.')
            ->assertJsonPath('data.router_id', $router->id)
            ->assertJsonPath('data.sync_type', 'pppoe')
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'router_sync.pppoe',
            'module' => 'router-sync',
        ]);
    }

    public function test_admin_bisa_sync_static(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter();

        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $this->mock(RouterSyncService::class, function (MockInterface $mock) use ($router): void {
            $mock->shouldReceive('syncStatic')
                ->once()
                ->andReturn($this->mockResult('static', $router));
        });

        $this->postJson('/api/v1/admin/router-sync/static', [
            'router_id' => $router->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sync_type', 'static')
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'router_sync.static',
            'module' => 'router-sync',
        ]);
    }

    public function test_admin_bisa_sync_address_list(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter();

        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $this->mock(RouterSyncService::class, function (MockInterface $mock) use ($router): void {
            $mock->shouldReceive('syncAddressList')
                ->once()
                ->andReturn($this->mockResult('address_list', $router));
        });

        $this->postJson('/api/v1/admin/router-sync/address-list', [
            'router_id' => $router->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sync_type', 'address_list')
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'router_sync.address_list',
            'module' => 'router-sync',
        ]);
    }

    public function test_admin_bisa_sync_all(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter();

        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $this->mock(RouterSyncService::class, function (MockInterface $mock) use ($router): void {
            $mock->shouldReceive('syncAll')
                ->once()
                ->andReturn($this->mockResult('all', $router));
        });

        $this->postJson('/api/v1/admin/router-sync/all', [
            'router_id' => $router->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sync_type', 'all')
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'router_sync.all',
            'module' => 'router-sync',
        ]);
    }

    public function test_router_id_kosong_gagal_validation(): void
    {
        $admin = User::factory()->admin()->create();
        $this->authenticateApi($admin);

        $this->postJson('/api/v1/admin/router-sync/pppoe', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['router_id']);
    }

    public function test_router_inactive_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter(isActive: false);

        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $this->postJson('/api/v1/admin/router-sync/static', [
            'router_id' => $router->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['router_id']);
    }

    public function test_unauthorized_ditolak(): void
    {
        $router = $this->createRouter();

        $this->postJson('/api/v1/admin/router-sync/all', [
            'router_id' => $router->id,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_response_selalu_envelope(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter();

        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $this->mock(RouterSyncService::class, function (MockInterface $mock) use ($router): void {
            $mock->shouldReceive('syncPppoe')
                ->once()
                ->andReturn($this->mockResult('pppoe', $router));
        });

        $this->postJson('/api/v1/admin/router-sync/pppoe', [
            'router_id' => $router->id,
        ])
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ]);
    }

    private function createRouter(bool $isActive = true): Router
    {
        return Router::query()->create([
            'router_code' => 'RTR-SYNC-'.strtoupper(substr(md5((string) microtime(true).random_int(1, 9999)), 0, 6)),
            'router_name' => 'Router Sync Test',
            'router_role' => 'bng',
            'mgmt_ip' => '10.99.0.'.random_int(2, 220),
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'location_name' => 'Lab',
            'is_active' => $isActive,
            'status' => $isActive ? 'active' : 'inactive',
        ]);
    }

    /**
     * @return array{success:bool,message:string,data:array<string,mixed>,meta:array<string,mixed>}
     */
    private function mockResult(string $type, Router $router): array
    {
        return [
            'success' => true,
            'message' => 'Sync '.$type.' dijalankan.',
            'data' => [
                'router_id' => $router->id,
                'sync_type' => $type,
            ],
            'meta' => [],
        ];
    }

    private function grantRouterAccess(User $user, Router $router): void
    {
        $user->accessibleRouters()->syncWithoutDetaching([$router->id]);
        $user->routers()->syncWithoutDetaching([$router->id]);
    }
}
