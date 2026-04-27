<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceIsolationStatus;
use App\Enums\ServiceIsolationType;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessServiceRouterOperationJob;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceIsolation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ManualIsolirControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_isolir_user_success(): void
    {
        $admin = User::factory()->superadmin()->create();
        $this->authenticateApi($admin);

        $service = $this->createService('SVC-MAN-ISO-01');

        $response = $this->postJson('/api/v1/admin/isolir/manual', [
            'router_id' => $service->router_id,
            'user_id' => $service->id,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.router_id', $service->router_id)
            ->assertJsonPath('data.isolation_type', ServiceIsolationType::Manual->value)
            ->assertJsonPath('data.status', ServiceIsolationStatus::Pending->value);

        $this->assertDatabaseHas('service_isolations', [
            'service_id' => $service->id,
            'router_id' => $service->router_id,
            'isolation_type' => ServiceIsolationType::Manual->value,
            'status' => ServiceIsolationStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'isolir.manual',
            'module' => 'isolations',
        ]);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 1);
    }

    public function test_release_user_success(): void
    {
        $admin = User::factory()->superadmin()->create();
        $this->authenticateApi($admin);

        $service = $this->createService('SVC-MAN-REL-01');
        $isolation = ServiceIsolation::query()->create([
            'service_id' => $service->id,
            'router_id' => $service->router_id,
            'isolation_type' => ServiceIsolationType::Manual->value,
            'address_list_name' => config('mikrotik.isolation.address_list_name'),
            'target_subnet' => $service->subnet_cidr,
            'status' => ServiceIsolationStatus::Pending->value,
        ]);

        $response = $this->postJson('/api/v1/admin/isolir/release', [
            'router_id' => $service->router_id,
            'user_id' => $service->pppoe_username,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $isolation->id)
            ->assertJsonPath('data.status', ServiceIsolationStatus::Released->value);

        $this->assertDatabaseHas('service_isolations', [
            'id' => $isolation->id,
            'status' => ServiceIsolationStatus::Released->value,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'release.manual',
            'module' => 'isolations',
        ]);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 1);
    }

    public function test_router_belum_dipilih_gagal(): void
    {
        $service = $this->createService('SVC-MAN-VAL-01');

        $this->postJson('/api/v1/admin/isolir/manual', [
            'user_id' => $service->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['router_id']);
    }

    public function test_unauthorized_user_ditolak(): void
    {
        $technician = User::factory()->technician()->create([
            'role' => UserRole::Technician->value,
        ]);

        $this->authenticateApi($technician);

        $this->postJson('/api/v1/admin/isolir/manual', [])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function createService(string $serviceCode): Service
    {
        $customer = Customer::query()->create([
            'customer_code' => 'CUST-'.$serviceCode,
            'full_name' => 'Customer '.$serviceCode,
            'phone' => '081200000000',
            'address' => 'Jl. Fiber '.$serviceCode,
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);

        $router = Router::query()->create([
            'router_code' => 'RTR-'.$serviceCode,
            'router_name' => 'Router '.$serviceCode,
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.20.'.random_int(10, 200),
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_active' => true,
        ]);

        $package = Package::query()->create([
            'package_name' => 'Package '.$serviceCode,
            'monthly_price' => 250000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        return Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'service_code' => $serviceCode,
            'monitor_vid' => 100,
            'monitor_pppoe_username' => strtolower('monitor.'.$serviceCode),
            'monitor_pppoe_password' => 'secret-monitor',
            'internet_vid' => random_int(200, 350),
            'subnet_cidr' => '10.30.40.0/29',
            'gateway_ip' => '10.30.40.1',
            'dhcp_pool_start' => '10.30.40.2',
            'dhcp_pool_end' => '10.30.40.6',
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'FTTH-'.$serviceCode,
            'pppoe_username' => strtolower('user.'.$serviceCode),
            'static_ip_address' => '10.30.40.2',
            'billing_status' => ServiceBillingStatus::Pending->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-01',
        ]);
    }
}
