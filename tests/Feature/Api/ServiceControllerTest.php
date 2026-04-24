<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\VidStatus;
use App\Enums\VidType;
use App\Models\Customer;
use App\Models\Olt;
use App\Models\Ont;
use App\Models\Package;
use App\Models\Router;
use App\Models\Service;
use App\Models\Vid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_service_and_assigns_the_selected_vid(): void
    {
        $customer = $this->createCustomer();
        $package = $this->createPackage();
        $router = $this->createRouter();
        $olt = $this->createOlt();
        $ont = $this->createOnt($olt);
        $vid = $this->createVid($router, 310);

        $response = $this->postJson('/api/v1/admin/services', [
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'olt_id' => $olt->id,
            'ont_id' => $ont->id,
            'vid_id' => $vid->id,
            'service_code' => 'SVC-FTTH-001',
            'monitor_vid' => 100,
            'monitor_pppoe_username' => 'monitor.ftth.001',
            'monitor_pppoe_password' => 'secret-monitor',
            'internet_vid' => 310,
            'subnet_cidr' => $this->subnetCidrForVid(310),
            'gateway_ip' => $this->gatewayIpForVid(310),
            'dhcp_pool_start' => $this->dhcpPoolStartForVid(310),
            'dhcp_pool_end' => $this->dhcpPoolEndForVid(310),
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 30,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'FTTH-CUST-001',
            'billing_status' => ServiceBillingStatus::Paid->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-21',
            'notes' => 'Initial activation',
        ]);

        $serviceId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.package_id', $package->id)
            ->assertJsonPath('data.router_id', $router->id)
            ->assertJsonPath('data.ont_id', $ont->id)
            ->assertJsonPath('data.vid_id', $vid->id)
            ->assertJsonPath('data.internet_vid', 310)
            ->assertJsonPath('data.monitor_pppoe_username', 'monitor.ftth.001')
            ->assertJsonPath('data.overall_status', ServiceOverallStatus::Active->value)
            ->assertJsonPath('data.vid.id', $vid->id);

        $this->assertDatabaseHas('services', [
            'id' => $serviceId,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'ont_id' => $ont->id,
            'vid_id' => $vid->id,
            'service_code' => 'SVC-FTTH-001',
            'monitor_pppoe_username' => 'monitor.ftth.001',
            'internet_vid' => 310,
            'overall_status' => ServiceOverallStatus::Active->value,
        ]);

        $this->assertDatabaseHas('vids', [
            'id' => $vid->id,
            'status' => VidStatus::Assigned->value,
            'customer_id' => $customer->id,
            'service_id' => $serviceId,
        ]);
    }

    public function test_it_rejects_a_vid_that_is_already_used_by_another_active_service(): void
    {
        $router = $this->createRouter();
        $package = $this->createPackage();
        $customerA = $this->createCustomer('CUST-A');
        $customerB = $this->createCustomer('CUST-B');
        $olt = $this->createOlt();
        $ontA = $this->createOnt($olt, 'ONT-SN-A');
        $ontB = $this->createOnt($olt, 'ONT-SN-B');
        $vid = $this->createVid($router, 320);

        $existingService = Service::query()->create([
            'customer_id' => $customerA->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'olt_id' => $olt->id,
            'ont_id' => $ontA->id,
            'vid_id' => $vid->id,
            'service_code' => 'SVC-EXISTING',
            'monitor_vid' => 100,
            'monitor_pppoe_username' => 'monitor.existing',
            'monitor_pppoe_password' => 'secret-existing',
            'internet_vid' => 320,
            'subnet_cidr' => $this->subnetCidrForVid(320),
            'gateway_ip' => $this->gatewayIpForVid(320),
            'dhcp_pool_start' => $this->dhcpPoolStartForVid(320),
            'dhcp_pool_end' => $this->dhcpPoolEndForVid(320),
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'FTTH-CUST-A',
            'billing_status' => ServiceBillingStatus::Paid->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-21',
        ]);

        $vid->update([
            'status' => VidStatus::Assigned->value,
            'customer_id' => $customerA->id,
            'service_id' => $existingService->id,
        ]);

        $response = $this->postJson('/api/v1/admin/services', [
            'customer_id' => $customerB->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'olt_id' => $olt->id,
            'ont_id' => $ontB->id,
            'vid_id' => $vid->id,
            'service_code' => 'SVC-NEW',
            'monitor_vid' => 101,
            'monitor_pppoe_username' => 'monitor.new',
            'monitor_pppoe_password' => 'secret-new',
            'internet_vid' => 320,
            'subnet_cidr' => $this->subnetCidrForVid(321),
            'gateway_ip' => $this->gatewayIpForVid(321),
            'dhcp_pool_start' => $this->dhcpPoolStartForVid(321),
            'dhcp_pool_end' => $this->dhcpPoolEndForVid(321),
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'FTTH-CUST-B',
            'billing_status' => ServiceBillingStatus::Paid->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-21',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vid_id']);
    }

    public function test_it_allows_reassigning_a_vid_from_inactive_service_to_a_new_active_service(): void
    {
        [$oldService, $vid] = $this->createAssignedService('SVC-OLD-INACTIVE', 'monitor.old.inactive', 322);
        $newCustomer = $this->createCustomer('CUST-NEW-VID');
        $newPackage = $this->createPackage('Reassign Package');
        $newOlt = $this->createOlt('OLT-REASSIGN');
        $newOnt = $this->createOnt($newOlt, 'ONT-REASSIGN');

        $oldService->update([
            'overall_status' => ServiceOverallStatus::Terminated->value,
            'network_status' => ServiceNetworkStatus::Inactive->value,
        ]);

        $response = $this->postJson('/api/v1/admin/services', [
            'customer_id' => $newCustomer->id,
            'package_id' => $newPackage->id,
            'router_id' => $oldService->router_id,
            'olt_id' => $newOlt->id,
            'ont_id' => $newOnt->id,
            'vid_id' => $vid->id,
            'service_code' => 'SVC-NEW-VID',
            'monitor_vid' => 101,
            'monitor_pppoe_username' => 'monitor.new.vid',
            'monitor_pppoe_password' => 'secret-new',
            'internet_vid' => 322,
            'subnet_cidr' => $this->subnetCidrForVid(322),
            'gateway_ip' => $this->gatewayIpForVid(322),
            'dhcp_pool_start' => $this->dhcpPoolStartForVid(322),
            'dhcp_pool_end' => $this->dhcpPoolEndForVid(322),
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'FTTH-CUST-NEW',
            'billing_status' => ServiceBillingStatus::Paid->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-21',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.vid_id', $vid->id)
            ->assertJsonPath('data.internet_vid', 322);

        $this->assertDatabaseHas('vids', [
            'id' => $vid->id,
            'status' => VidStatus::Assigned->value,
            'customer_id' => $newCustomer->id,
            'service_id' => $response->json('data.id'),
        ]);

        $this->assertDatabaseHas('services', [
            'id' => $oldService->id,
            'vid_id' => $vid->id,
            'overall_status' => ServiceOverallStatus::Terminated->value,
        ]);
    }

    public function test_it_releases_vid_when_a_service_is_marked_inactive(): void
    {
        [$service, $vid] = $this->createAssignedService();

        $response = $this->putJson("/api/v1/admin/services/{$service->id}", [
            'customer_id' => $service->customer_id,
            'package_id' => $service->package_id,
            'router_id' => $service->router_id,
            'olt_id' => $service->olt_id,
            'ont_id' => $service->ont_id,
            'vid_id' => $service->vid_id,
            'service_code' => $service->service_code,
            'monitor_vid' => $service->monitor_vid,
            'monitor_pppoe_username' => $service->monitor_pppoe_username,
            'monitor_pppoe_password' => $service->monitor_pppoe_password,
            'internet_vid' => $service->internet_vid,
            'subnet_cidr' => $service->subnet_cidr,
            'gateway_ip' => $service->gateway_ip,
            'dhcp_pool_start' => $service->dhcp_pool_start,
            'dhcp_pool_end' => $service->dhcp_pool_end,
            'ip_pool_count' => $service->ip_pool_count,
            'rate_limit_mbps' => $service->rate_limit_mbps,
            'isolation_method' => $service->isolation_method->value,
            'address_list_name' => $service->address_list_name,
            'billing_status' => ServiceBillingStatus::Suspended->value,
            'network_status' => ServiceNetworkStatus::Inactive->value,
            'overall_status' => ServiceOverallStatus::Inactive->value,
            'activation_date' => $service->activation_date?->toDateString(),
            'notes' => 'Service deactivated',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.overall_status', ServiceOverallStatus::Inactive->value);

        $this->assertDatabaseHas('vids', [
            'id' => $vid->id,
            'status' => VidStatus::Available->value,
            'customer_id' => null,
            'service_id' => null,
        ]);
    }

    public function test_it_soft_deletes_a_service_and_releases_the_vid(): void
    {
        [$service, $vid] = $this->createAssignedService('SVC-DELETE', 'monitor.delete', 330);

        $response = $this->deleteJson("/api/v1/admin/services/{$service->id}");

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Service archived successfully.');

        $this->assertSoftDeleted('services', [
            'id' => $service->id,
            'service_code' => 'SVC-DELETE',
        ]);

        $this->assertDatabaseHas('vids', [
            'id' => $vid->id,
            'status' => VidStatus::Available->value,
            'customer_id' => null,
            'service_id' => null,
        ]);
    }

    private function createAssignedService(
        string $serviceCode = 'SVC-UPDATE',
        string $monitorUsername = 'monitor.update',
        int $internetVid = 325,
    ): array {
        $customer = $this->createCustomer($serviceCode.'-CUST');
        $package = $this->createPackage($serviceCode.' Package');
        $router = $this->createRouter($serviceCode.'-RTR');
        $olt = $this->createOlt($serviceCode.'-OLT');
        $ont = $this->createOnt($olt, $serviceCode.'-ONT');
        $vid = $this->createVid($router, $internetVid);

        $service = Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'olt_id' => $olt->id,
            'ont_id' => $ont->id,
            'vid_id' => $vid->id,
            'service_code' => $serviceCode,
            'monitor_vid' => 100,
            'monitor_pppoe_username' => $monitorUsername,
            'monitor_pppoe_password' => 'secret',
            'internet_vid' => $internetVid,
            'subnet_cidr' => $this->subnetCidrForVid($internetVid),
            'gateway_ip' => $this->gatewayIpForVid($internetVid),
            'dhcp_pool_start' => $this->dhcpPoolStartForVid($internetVid),
            'dhcp_pool_end' => $this->dhcpPoolEndForVid($internetVid),
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => $serviceCode.'-LIST',
            'billing_status' => ServiceBillingStatus::Paid->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-21',
        ]);

        $vid->update([
            'status' => VidStatus::Assigned->value,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
        ]);

        return [$service, $vid->refresh()];
    }

    private function createCustomer(string $customerCode = 'CUST-001'): Customer
    {
        return Customer::query()->create([
            'customer_code' => $customerCode,
            'full_name' => 'Customer '.$customerCode,
            'phone' => '08123456789',
            'address' => 'Jl. Testing No. 1',
            'customer_type' => CustomerType::Business->value,
            'status' => CustomerStatus::Active->value,
        ]);
    }

    private function createPackage(string $packageName = 'Dedicated FTTH 20 Mbps'): Package
    {
        return Package::query()->create([
            'package_name' => $packageName,
            'monthly_price' => 350000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'description' => 'Test package',
            'is_active' => true,
        ]);
    }

    private function createRouter(string $routerCode = 'RTR-FTTH'): Router
    {
        return Router::query()->create([
            'router_code' => $routerCode,
            'router_name' => 'Router '.$routerCode,
            'router_role' => 'bng',
            'mgmt_ip' => '10.0.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'location_name' => 'HQ',
            'is_active' => true,
        ]);
    }

    private function createOlt(string $oltCode = 'OLT-001'): Olt
    {
        return Olt::query()->create([
            'olt_code' => $oltCode,
            'olt_name' => 'OLT '.$oltCode,
            'mgmt_ip' => '10.1.0.1',
            'vendor_name' => 'ZTE',
            'location_name' => 'POP A',
            'is_active' => true,
        ]);
    }

    private function createOnt(Olt $olt, string $ontSn = 'ONT-SN-001'): Ont
    {
        return Ont::query()->create([
            'olt_id' => $olt->id,
            'ont_sn' => $ontSn,
            'ont_name' => 'ONT '.$ontSn,
            'pon_port' => '1/1/1',
            'onu_id' => 1,
            'ssid_name' => 'TEST-WIFI',
            'wifi_password' => 'password123',
            'optical_status' => 'good',
            'rx_power' => -20.50,
            'tx_power' => 2.50,
            'genieacs_device_id' => 'device-'.$ontSn,
            'status' => 'online',
            'last_seen_at' => now(),
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
            'sync_source' => 'seed',
            'status' => VidStatus::Available->value,
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
