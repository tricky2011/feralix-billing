<?php

namespace Tests\Feature;

use App\Contracts\Mikrotik\MikrotikApiClient;
use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceIsolationStatus;
use App\Enums\ServiceIsolationType;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\ServiceRouterOperationJobStatus;
use App\Enums\ServiceRouterOperationLogAction;
use App\Jobs\ProcessServiceRouterOperationJob;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceIsolation;
use App\Models\ServiceRouterOperationJob;
use App\Models\Vid;
use App\Services\Provisioning\ServiceIsolationRouterExecutionService;
use App\Services\Provisioning\ServiceIsolationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServiceRouterOperationJobTest extends TestCase
{
    use RefreshDatabase;

    private FeatureFakeMikrotikApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->client = new FeatureFakeMikrotikApiClient();
        $this->app->bind(MikrotikApiClientFactory::class, fn () => new FeatureFakeMikrotikApiClientFactory($this->client));
    }

    public function test_it_applies_pending_isolation_to_mikrotik_and_marks_it_applied(): void
    {
        $service = $this->createService('SVC-REAL-ISO-001');

        $isolation = app(ServiceIsolationService::class)->createIsolationRecord([
            'service_id' => $service->id,
            'notes' => 'Real isolation requested.',
        ]);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 1);

        $operationJob = ServiceRouterOperationJob::query()->latest('id')->firstOrFail();

        app(ServiceIsolationRouterExecutionService::class)->execute($operationJob->id);

        $isolation->refresh();
        $service->refresh();

        $this->assertSame(ServiceIsolationStatus::Applied, $isolation->status);
        $this->assertSame(ServiceNetworkStatus::Isolated, $service->network_status);
        $this->assertSame(ServiceOverallStatus::Isolated, $service->overall_status);

        $this->assertDatabaseHas('service_router_operation_jobs', [
            'id' => $operationJob->id,
            'job_status' => ServiceRouterOperationJobStatus::Completed->value,
            'address_list_name' => config('mikrotik.isolation.address_list_name'),
            'target_address' => '10.20.30.0/29',
        ]);

        $this->assertDatabaseHas('service_router_operation_logs', [
            'operation_job_id' => $operationJob->id,
            'action_type' => ServiceRouterOperationLogAction::Added->value,
            'target_address' => '10.20.30.0/29',
        ]);

        $this->assertDatabaseHas('service_router_operation_statuses', [
            'service_id' => $service->id,
            'address_list_name' => config('mikrotik.isolation.address_list_name'),
            'address_list_target' => '10.20.30.0/29',
            'address_list_found' => 1,
        ]);

        $this->assertCount(1, $this->client->entries);
    }

    public function test_it_releases_isolation_and_ignores_missing_address_list_entries(): void
    {
        $service = $this->createService('SVC-REAL-REL-001');

        $isolation = ServiceIsolation::query()->create([
            'service_id' => $service->id,
            'router_id' => $service->router_id,
            'isolation_type' => ServiceIsolationType::Manual->value,
            'address_list_name' => config('mikrotik.isolation.address_list_name'),
            'target_subnet' => $service->subnet_cidr,
            'target_type' => 'subnet',
            'target_identifier' => $service->subnet_cidr,
            'target_payload' => [
                'access_mode' => 'vlan',
                'isolation_method' => ServiceIsolationMethod::AddressList->value,
            ],
            'redirect_url' => config('billing.ftth_isolation.default_redirect_url'),
            'status' => ServiceIsolationStatus::Applied->value,
            'isolated_at' => now()->subMinute(),
        ]);

        $service->update([
            'network_status' => ServiceNetworkStatus::Isolated->value,
            'overall_status' => ServiceOverallStatus::Isolated->value,
        ]);

        app(ServiceIsolationService::class)->releaseIsolation($isolation, [
            'released_at' => now(),
            'notes' => 'Released from panel.',
        ]);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 1);

        $operationJob = ServiceRouterOperationJob::query()->latest('id')->firstOrFail();

        app(ServiceIsolationRouterExecutionService::class)->execute($operationJob->id);

        $this->assertDatabaseHas('service_router_operation_jobs', [
            'id' => $operationJob->id,
            'job_status' => ServiceRouterOperationJobStatus::Completed->value,
            'target_address' => '10.20.30.0/29',
        ]);

        $this->assertDatabaseHas('service_router_operation_logs', [
            'operation_job_id' => $operationJob->id,
            'action_type' => ServiceRouterOperationLogAction::AlreadyAbsent->value,
            'target_address' => '10.20.30.0/29',
        ]);

        $this->assertDatabaseHas('service_router_operation_statuses', [
            'service_id' => $service->id,
            'address_list_name' => config('mikrotik.isolation.address_list_name'),
            'address_list_target' => null,
            'address_list_found' => 0,
        ]);
    }

    private function createService(string $serviceCode): Service
    {
        $customer = Customer::query()->create([
            'customer_code' => 'CUST-'.$serviceCode,
            'full_name' => 'Customer '.$serviceCode,
            'phone' => '081234567890',
            'address' => 'Jl. Fiber '.$serviceCode,
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);

        $package = Package::query()->create([
            'package_name' => 'Package '.$serviceCode,
            'monthly_price' => 200000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        $router = Router::query()->create([
            'router_code' => 'RTR-'.$serviceCode,
            'router_name' => 'Router '.$serviceCode,
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.30.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_active' => true,
        ]);

        $vid = Vid::query()->create([
            'router_id' => $router->id,
            'vid' => 310,
            'subnet_cidr' => '10.20.30.0/29',
            'gateway_ip' => '10.20.30.1',
            'pool_start_ip' => '10.20.30.2',
            'pool_end_ip' => '10.20.30.6',
        ]);

        return Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'vid_id' => $vid->id,
            'service_code' => $serviceCode,
            'monitor_vid' => 100,
            'monitor_pppoe_username' => strtolower('monitor.'.$serviceCode),
            'monitor_pppoe_password' => 'secret-monitor',
            'internet_vid' => 310,
            'subnet_cidr' => '10.20.30.0/29',
            'gateway_ip' => '10.20.30.1',
            'dhcp_pool_start' => '10.20.30.2',
            'dhcp_pool_end' => '10.20.30.6',
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'FTTH-'.$serviceCode,
            'billing_status' => ServiceBillingStatus::Pending->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-01',
        ]);
    }
}

class FeatureFakeMikrotikApiClientFactory implements MikrotikApiClientFactory
{
    public function __construct(private readonly FeatureFakeMikrotikApiClient $client) {}

    public function forRouter(Router $router): MikrotikApiClient
    {
        return $this->client;
    }
}

class FeatureFakeMikrotikApiClient implements MikrotikApiClient
{
    /** @var list<array<string, string>> */
    public array $entries = [];

    private int $sequence = 0;

    public function print(string $menuPath, array $properties = [], array $where = []): array
    {
        if ('/ip/firewall/address-list' !== '/'.trim($menuPath, '/')) {
            return [];
        }

        return array_values(array_filter($this->entries, static function (array $entry) use ($where): bool {
            foreach ($where as $key => $value) {
                if (($entry[$key] ?? null) !== (string) $value) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function add(string $menuPath, array $attributes = []): void
    {
        $this->sequence++;
        $this->entries[] = [
            '.id' => '*'.$this->sequence,
            'list' => (string) ($attributes['list'] ?? ''),
            'address' => (string) ($attributes['address'] ?? ''),
            'comment' => (string) ($attributes['comment'] ?? ''),
        ];
    }

    public function remove(string $menuPath, string $id): void
    {
        $this->entries = array_values(array_filter(
            $this->entries,
            static fn (array $entry): bool => ($entry['.id'] ?? null) !== $id
        ));
    }

    public function disconnect(): void
    {
        // No-op for fake client.
    }
}
