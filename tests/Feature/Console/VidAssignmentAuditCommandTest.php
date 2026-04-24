<?php

namespace Tests\Feature\Console;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\VidStatus;
use App\Enums\VidType;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\Service;
use App\Models\Vid;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VidAssignmentAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_and_repairs_service_vid_mismatch(): void
    {
        $customer = $this->createCustomer();
        $package = $this->createPackage();
        $router = $this->createRouter();
        $vid = $this->createVid($router, 360);
        $service = $this->createService($customer, $package, $router, $vid);

        $this->artisan('services:audit-vid-assignments')
            ->expectsOutputToContain('service_vid_mismatch: 1')
            ->expectsOutputToContain('vid_status_not_assigned')
            ->assertExitCode(Command::FAILURE);

        $this->artisan('services:audit-vid-assignments', ['--repair' => true])
            ->expectsOutputToContain('synced_services=1')
            ->expectsOutputToContain('total_issues: 0')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('vids', [
            'id' => $vid->id,
            'status' => VidStatus::Assigned->value,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_it_detects_duplicate_active_services_using_the_same_vid(): void
    {
        $customerA = $this->createCustomer('CUST-DUP-A');
        $customerB = $this->createCustomer('CUST-DUP-B');
        $package = $this->createPackage();
        $router = $this->createRouter();
        $vid = $this->createVid($router, 370);

        $serviceA = $this->createService($customerA, $package, $router, $vid, 'SVC-DUP-A', 'monitor.dup.a');
        $this->createService($customerB, $package, $router, $vid, 'SVC-DUP-B', 'monitor.dup.b');

        $vid->update([
            'status' => VidStatus::Assigned->value,
            'customer_id' => $customerA->id,
            'service_id' => $serviceA->id,
        ]);

        $this->artisan('services:audit-vid-assignments')
            ->expectsOutputToContain('duplicate_active_vid: 1')
            ->expectsOutputToContain('duplicate_active_router_vid: 1')
            ->assertExitCode(Command::FAILURE);
    }

    private function createCustomer(string $customerCode = 'CUST-AUDIT-001'): Customer
    {
        return Customer::query()->create([
            'customer_code' => $customerCode,
            'full_name' => 'Customer '.$customerCode,
            'phone' => '08123456789',
            'address' => 'Jl. Audit No. 1',
            'customer_type' => CustomerType::Business->value,
            'status' => CustomerStatus::Active->value,
        ]);
    }

    private function createPackage(): Package
    {
        return Package::query()->create([
            'package_name' => 'Audit Package',
            'monthly_price' => 250000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'description' => 'Audit package',
            'is_active' => true,
        ]);
    }

    private function createRouter(): Router
    {
        return Router::query()->create([
            'router_code' => 'RTR-AUDIT',
            'router_name' => 'Router Audit',
            'router_role' => 'bng',
            'mgmt_ip' => '10.0.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'location_name' => 'HQ',
            'is_active' => true,
        ]);
    }

    private function createVid(Router $router, int $vidNumber): Vid
    {
        return Vid::query()->create([
            'router_id' => $router->id,
            'vid' => $vidNumber,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => $this->subnetCidrForVid($vidNumber),
            'gateway_ip' => $this->gatewayIpForVid($vidNumber),
            'pool_start_ip' => $this->dhcpPoolStartForVid($vidNumber),
            'pool_end_ip' => $this->dhcpPoolEndForVid($vidNumber),
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 20,
            'sync_source' => 'test',
            'status' => VidStatus::Available->value,
        ]);
    }

    private function createService(
        Customer $customer,
        Package $package,
        Router $router,
        Vid $vid,
        string $serviceCode = 'SVC-AUDIT-001',
        string $monitorUsername = 'monitor.audit.001',
    ): Service {
        return Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'vid_id' => $vid->id,
            'service_code' => $serviceCode,
            'monitor_vid' => 100,
            'monitor_pppoe_username' => $monitorUsername,
            'monitor_pppoe_password' => 'secret',
            'internet_vid' => $vid->vid,
            'subnet_cidr' => $vid->subnet_cidr,
            'gateway_ip' => $vid->gateway_ip,
            'dhcp_pool_start' => $vid->pool_start_ip,
            'dhcp_pool_end' => $vid->pool_end_ip,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => $serviceCode,
            'billing_status' => ServiceBillingStatus::Paid->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-21',
        ]);
    }

    private function subnetCidrForVid(int $vidNumber): string
    {
        [$secondOctet, $thirdOctet] = $this->networkOctetsForVid($vidNumber);

        return sprintf('10.%d.%d.0/29', $secondOctet, $thirdOctet);
    }

    private function gatewayIpForVid(int $vidNumber): string
    {
        [$secondOctet, $thirdOctet] = $this->networkOctetsForVid($vidNumber);

        return sprintf('10.%d.%d.1', $secondOctet, $thirdOctet);
    }

    private function dhcpPoolStartForVid(int $vidNumber): string
    {
        [$secondOctet, $thirdOctet] = $this->networkOctetsForVid($vidNumber);

        return sprintf('10.%d.%d.2', $secondOctet, $thirdOctet);
    }

    private function dhcpPoolEndForVid(int $vidNumber): string
    {
        [$secondOctet, $thirdOctet] = $this->networkOctetsForVid($vidNumber);

        return sprintf('10.%d.%d.6', $secondOctet, $thirdOctet);
    }

    private function networkOctetsForVid(int $vidNumber): array
    {
        return [intdiv($vidNumber, 256), $vidNumber % 256];
    }
}
