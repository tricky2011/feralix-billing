<?php

namespace Tests\Feature\Api;

use App\Models\Router;
use App\Models\User;
use App\Services\Mikrotik\RouterStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class RouterStatsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipApiAuthentication = true;

    public function test_admin_bisa_get_router_stats(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter();

        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $this->mock(RouterStatsService::class, function (MockInterface $mock) use ($router): void {
            $mock->shouldReceive('getRouterStats')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message' => 'Router stats retrieved successfully.',
                    'data' => [
                        'router_id' => $router->id,
                        'router_code' => $router->router_code,
                        'router_name' => $router->router_name,
                        'pppoe' => [
                            'total_secrets' => 100,
                            'active_secrets' => 80,
                            'active_users' => 45,
                        ],
                        'dhcp' => [
                            'total_leases' => 50,
                            'bound_leases' => 30,
                        ],
                        'ip_pool' => [
                            'total_pools' => 10,
                            'total_addresses' => 256,
                        ],
                        'interfaces' => [
                            'total' => 50,
                            'active' => 45,
                            'vlan_count' => 10,
                        ],
                        'addresses' => [
                            'total' => 100,
                            'active' => 80,
                        ],
                    ],
                ]);
        });

        $response = $this->getJson("/api/v1/admin/routers/{$router->id}/stats");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.router_id', $router->id)
            ->assertJsonPath('data.pppoe.active_secrets', 80)
            ->assertJsonPath('data.pppoe.active_users', 45)
            ->assertJsonPath('data.dhcp.bound_leases', 30)
            ->assertJsonPath('data.ip_pool.total_addresses', 256);
    }

    public function test_unauthorized_ditolak(): void
    {
        $router = $this->createRouter();

        $this->getJson("/api/v1/admin/routers/{$router->id}/stats")
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    private function createRouter(bool $isActive = true): Router
    {
        return Router::query()->create([
            'router_code' => 'RTR-STATS-'.strtoupper(substr(md5((string) microtime(true).random_int(1, 9999)), 0, 6)),
            'router_name' => 'Router Stats Test',
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

    private function grantRouterAccess(User $user, Router $router): void
    {
        $user->accessibleRouters()->syncWithoutDetaching([$router->id]);
        $user->routers()->syncWithoutDetaching([$router->id]);
    }
}
