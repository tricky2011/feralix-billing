<?php

namespace Tests\Feature\Console;

use App\Contracts\Mikrotik\MikrotikApiClient;
use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\MonitorPppoeStatus;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceMonitorPppoeStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SyncPppoeMonitorCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-22 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_marks_monitor_sessions_offline_and_forces_service_status_down(): void
    {
        $router = $this->createRouter('RTR-MON-OFF');
        $service = $this->createService(
            router: $router,
            serviceCode: 'SVC-MON-OFF-001',
            monitorUsername: 'monitor.off.001',
            overallStatus: ServiceOverallStatus::Active,
            networkStatus: ServiceNetworkStatus::Active,
        );

        ServiceMonitorPppoeStatus::query()->create([
            'service_id' => $service->id,
            'router_id' => $router->id,
            'monitor_pppoe_username' => 'monitor.off.001',
            'status' => MonitorPppoeStatus::Online->value,
            'base_overall_status' => ServiceOverallStatus::Active->value,
            'last_seen_at' => Carbon::parse('2026-04-22 09:55:00'),
            'last_synced_at' => Carbon::parse('2026-04-22 09:55:00'),
            'last_status_changed_at' => Carbon::parse('2026-04-22 09:40:00'),
            'session_info' => [
                'address' => '172.16.10.2',
            ],
        ]);

        $this->bindApiClientFactory($this->fakeApiClient([]));

        $this->artisan('monitor:sync-pppoe', ['router' => $router->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'overall_status' => ServiceOverallStatus::Down->value,
        ]);

        $this->assertDatabaseHas('service_monitor_pppoe_statuses', [
            'service_id' => $service->id,
            'router_id' => $router->id,
            'monitor_pppoe_username' => 'monitor.off.001',
            'status' => MonitorPppoeStatus::Offline->value,
            'base_overall_status' => ServiceOverallStatus::Active->value,
            'last_seen_at' => '2026-04-22 09:55:00',
            'last_synced_at' => '2026-04-22 10:00:00',
            'last_status_changed_at' => '2026-04-22 10:00:00',
        ]);

        $this->assertDatabaseHas('service_monitor_pppoe_logs', [
            'service_id' => $service->id,
            'router_id' => $router->id,
            'monitor_pppoe_username' => 'monitor.off.001',
            'previous_status' => MonitorPppoeStatus::Online->value,
            'status' => MonitorPppoeStatus::Offline->value,
            'last_seen_at' => '2026-04-22 09:55:00',
            'observed_at' => '2026-04-22 10:00:00',
        ]);
    }

    public function test_it_marks_monitor_sessions_online_and_restores_desired_service_status(): void
    {
        $router = $this->createRouter('RTR-MON-ON');
        $service = $this->createService(
            router: $router,
            serviceCode: 'SVC-MON-ON-001',
            monitorUsername: 'monitor.on.001',
            overallStatus: ServiceOverallStatus::Down,
            networkStatus: ServiceNetworkStatus::Isolated,
        );

        ServiceMonitorPppoeStatus::query()->create([
            'service_id' => $service->id,
            'router_id' => $router->id,
            'monitor_pppoe_username' => 'monitor.on.001',
            'status' => MonitorPppoeStatus::Offline->value,
            'base_overall_status' => ServiceOverallStatus::Isolated->value,
            'last_seen_at' => Carbon::parse('2026-04-22 08:00:00'),
            'last_synced_at' => Carbon::parse('2026-04-22 09:30:00'),
            'last_status_changed_at' => Carbon::parse('2026-04-22 09:00:00'),
        ]);

        $this->bindApiClientFactory($this->fakeApiClient([
            [
                '.id' => '*A1',
                'name' => 'monitor.on.001',
                'service' => 'pppoe-monitor',
                'caller-id' => 'AA:BB:CC:DD:EE:FF',
                'address' => '172.16.20.2',
                'uptime' => '15m20s',
                'session-id' => 'session-001',
            ],
        ]));

        $this->artisan('monitor:sync-pppoe', ['router' => $router->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'overall_status' => ServiceOverallStatus::Isolated->value,
        ]);

        $this->assertDatabaseHas('service_monitor_pppoe_statuses', [
            'service_id' => $service->id,
            'router_id' => $router->id,
            'monitor_pppoe_username' => 'monitor.on.001',
            'status' => MonitorPppoeStatus::Online->value,
            'base_overall_status' => ServiceOverallStatus::Isolated->value,
            'last_seen_at' => '2026-04-22 10:00:00',
            'last_synced_at' => '2026-04-22 10:00:00',
            'last_status_changed_at' => '2026-04-22 10:00:00',
        ]);

        $this->assertDatabaseHas('service_monitor_pppoe_logs', [
            'service_id' => $service->id,
            'router_id' => $router->id,
            'monitor_pppoe_username' => 'monitor.on.001',
            'previous_status' => MonitorPppoeStatus::Offline->value,
            'status' => MonitorPppoeStatus::Online->value,
            'last_seen_at' => '2026-04-22 10:00:00',
            'observed_at' => '2026-04-22 10:00:00',
        ]);

        $statusRow = ServiceMonitorPppoeStatus::query()
            ->where('service_id', $service->id)
            ->firstOrFail();

        $this->assertSame([
            'remote_id' => '*A1',
            'service' => 'pppoe-monitor',
            'caller_id' => 'AA:BB:CC:DD:EE:FF',
            'address' => '172.16.20.2',
            'uptime' => '15m20s',
            'session_id' => 'session-001',
        ], $statusRow->session_info);
    }

    public function test_it_keeps_existing_service_state_when_router_query_fails(): void
    {
        $router = $this->createRouter('RTR-MON-FAIL');
        $service = $this->createService(
            router: $router,
            serviceCode: 'SVC-MON-FAIL-001',
            monitorUsername: 'monitor.fail.001',
            overallStatus: ServiceOverallStatus::Active,
            networkStatus: ServiceNetworkStatus::Active,
        );

        $this->bindApiClientFactory($this->fakeApiClient([], new RuntimeException('Router API timeout')));

        $this->artisan('monitor:sync-pppoe', ['router' => $router->id])
            ->assertFailed();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'overall_status' => ServiceOverallStatus::Active->value,
        ]);

        $this->assertDatabaseMissing('service_monitor_pppoe_statuses', [
            'service_id' => $service->id,
        ]);

        $this->assertDatabaseMissing('service_monitor_pppoe_logs', [
            'service_id' => $service->id,
        ]);
    }

    private function bindApiClientFactory(MikrotikApiClient $client): void
    {
        $factory = Mockery::mock(MikrotikApiClientFactory::class);
        $factory
            ->shouldReceive('forRouter')
            ->andReturn($client);

        $this->app->instance(MikrotikApiClientFactory::class, $factory);
    }

    private function fakeApiClient(array $records, ?\Throwable $throwable = null): MikrotikApiClient
    {
        return new class($records, $throwable) implements MikrotikApiClient
        {
            public function __construct(
                private readonly array $records,
                private readonly ?\Throwable $throwable,
            ) {}

            public function print(string $menuPath, array $properties = []): array
            {
                if ($this->throwable !== null) {
                    throw $this->throwable;
                }

                return $this->records;
            }

            public function disconnect(): void
            {
            }
        };
    }

    private function createRouter(string $routerCode): Router
    {
        return Router::query()->create([
            'router_code' => $routerCode,
            'router_name' => 'Router '.$routerCode,
            'router_role' => 'bng',
            'mgmt_ip' => '10.255.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'location_name' => 'POP Monitoring',
            'is_active' => true,
        ]);
    }

    private function createService(
        Router $router,
        string $serviceCode,
        string $monitorUsername,
        ServiceOverallStatus $overallStatus,
        ServiceNetworkStatus $networkStatus,
    ): Service {
        $customer = Customer::query()->create([
            'customer_code' => 'CUST-'.$serviceCode,
            'full_name' => 'Customer '.$serviceCode,
            'phone' => '08123456789',
            'address' => 'Jl. Monitor No. 1',
            'customer_type' => CustomerType::Business->value,
            'status' => CustomerStatus::Active->value,
        ]);

        $package = Package::query()->create([
            'package_name' => 'Package '.$serviceCode,
            'monthly_price' => 350000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'description' => 'Monitoring package',
            'is_active' => true,
        ]);

        return Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'service_code' => $serviceCode,
            'monitor_vid' => 100,
            'monitor_pppoe_username' => $monitorUsername,
            'monitor_pppoe_password' => 'secret-monitor',
            'internet_vid' => 220,
            'subnet_cidr' => '10.10.20.0/29',
            'gateway_ip' => '10.10.20.1',
            'dhcp_pool_start' => '10.10.20.2',
            'dhcp_pool_end' => '10.10.20.6',
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'ADDR-'.$serviceCode,
            'billing_status' => ServiceBillingStatus::Paid->value,
            'network_status' => $networkStatus->value,
            'overall_status' => $overallStatus->value,
            'activation_date' => '2026-04-01',
        ]);
    }
}
