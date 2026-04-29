<?php

namespace Tests\Feature\Api;

use App\Data\Mikrotik\MikrotikIpPoolRecord;
use App\Models\Router;
use App\Models\User;
use App\Services\Mikrotik\IpPoolService;
use App\Services\Mikrotik\IpPoolVidAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class IpPoolControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipApiAuthentication = true;

    public function test_admin_bisa_get_ip_pools_list(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter();

        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $this->mock(IpPoolService::class, function (MockInterface $mock) use ($router): void {
            $mock->shouldReceive('fetchFromRouter')
                ->once()
                ->andReturn([
                    new MikrotikIpPoolRecord(
                        name: 'pool-cust-110',
                        ranges: [['start_ip' => '10.10.110.2', 'end_ip' => '10.10.110.6']],
                        totalIps: 5,
                        usedIps: 2,
                        vlanId: 110,
                        vlanName: 'cust-110',
                    ),
                ]);
        });

        $response = $this->getJson("/api/v1/admin/routers/{$router->id}/ip-pools");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_bisa_get_ip_pools_summary(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter();

        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $this->mock(IpPoolService::class, function (MockInterface $mock) use ($router): void {
            $mock->shouldReceive('getPoolStatusSummary')
                ->once()
                ->andReturn([
                    'router_id' => $router->id,
                    'router_code' => $router->router_code,
                    'router_name' => $router->router_name,
                    'provider' => 'routeros-api',
                    'total_pools' => 10,
                    'total_ips' => 256,
                    'total_used_ips' => 100,
                    'total_free_ips' => 156,
                    'overall_usage_percentage' => 39.06,
                    'pools_with_vlan' => 8,
                    'pools_without_vlan' => 2,
                    'full_pools' => 1,
                    'available_pools' => 9,
                ]);
        });

        $response = $this->getJson("/api/v1/admin/routers/{$router->id}/ip-pools/summary");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_pools', 10)
            ->assertJsonPath('data.total_ips', 256)
            ->assertJsonPath('data.total_free_ips', 156);
    }

    public function test_admin_bisa_get_ip_pools_utilization(): void
    {
        $admin = User::factory()->admin()->create();
        $router = $this->createRouter();

        $this->grantRouterAccess($admin, $router);
        $this->authenticateApi($admin);

        $this->mock(IpPoolService::class, function (MockInterface $mock) use ($router): void {
            $mock->shouldReceive('getPoolUtilizationByVid')
                ->once()
                ->andReturn([
                    110 => [
                        'vlan_id' => 110,
                        'pool_name' => 'pool-cust-110',
                        'free_ips' => 3,
                        'total_ips' => 5,
                        'usage_percentage' => 40.0,
                    ],
                ]);

            $mock->shouldReceive('suggestAvailableVids')
                ->once()
                ->andReturn([
                    [
                        'vlan_id' => 110,
                        'free_ips' => 3,
                        'total_ips' => 5,
                        'usage_percentage' => 40.0,
                        'pool_count' => 1,
                    ],
                ]);
        });

        $response = $this->getJson("/api/v1/admin/routers/{$router->id}/ip-pools/utilization");

        $response
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_unauthorized_ditolak(): void
    {
        $router = $this->createRouter();

        $this->getJson("/api/v1/admin/routers/{$router->id}/ip-pools")
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    private function createRouter(bool $isActive = true): Router
    {
        return Router::query()->create([
            'router_code' => 'RTR-POOL-'.strtoupper(substr(md5((string) microtime(true).random_int(1, 9999)), 0, 6)),
            'router_name' => 'Router Pool Test',
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
