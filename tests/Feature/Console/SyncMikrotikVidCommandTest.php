<?php

namespace Tests\Feature\Console;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\MikrotikSyncJobStatus;
use App\Enums\MikrotikSyncVidActionType;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\VidStatus;
use App\Enums\VidType;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\RouterScope;
use App\Models\Service;
use App\Models\Vid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncMikrotikVidCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_new_vids_and_logs_sync_results_from_fake_provider(): void
    {
        $router = $this->createRouter('RTR-SYNC-A');
        $scope = $this->createScope($router, 100, 199);

        config()->set('mikrotik.fake.routers.'.$router->router_code, [
            [
                'vid' => 110,
                'vlan_name' => 'cust-110',
                'subnet_cidr' => '10.10.110.0/29',
                'gateway_ip' => '10.10.110.1',
                'pool_start_ip' => '10.10.110.2',
                'pool_end_ip' => '10.10.110.6',
            ],
            [
                'vid' => 130,
                'vlan_name' => 'cust-130-pending',
                'subnet_cidr' => null,
                'gateway_ip' => null,
                'pool_start_ip' => null,
                'pool_end_ip' => null,
            ],
        ]);

        $this->artisan('mikrotik:sync-vids', ['router' => $router->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('mikrotik_sync_jobs', [
            'router_id' => $router->id,
            'job_status' => MikrotikSyncJobStatus::Completed->value,
            'total_found' => 2,
            'total_new' => 2,
            'total_updated' => 0,
            'total_conflict' => 0,
        ]);

        $this->assertDatabaseHas('vids', [
            'router_id' => $router->id,
            'scope_id' => $scope->id,
            'vid' => 110,
            'status' => VidStatus::Unregistered->value,
            'subnet_cidr' => '10.10.110.0/29',
            'gateway_ip' => '10.10.110.1',
            'pool_start_ip' => '10.10.110.2',
            'pool_end_ip' => '10.10.110.6',
            'sync_source' => 'mikrotik:fake',
        ]);

        $this->assertDatabaseHas('vids', [
            'router_id' => $router->id,
            'scope_id' => $scope->id,
            'vid' => 130,
            'status' => VidStatus::Unknown->value,
            'subnet_cidr' => null,
            'gateway_ip' => null,
            'pool_start_ip' => null,
            'pool_end_ip' => null,
            'sync_source' => 'mikrotik:fake',
        ]);

        $this->assertDatabaseHas('mikrotik_sync_vid_logs', [
            'router_id' => $router->id,
            'vid' => 110,
            'action_type' => MikrotikSyncVidActionType::Created->value,
            'conflict_flag' => 0,
        ]);

        $this->assertDatabaseHas('mikrotik_sync_vid_logs', [
            'router_id' => $router->id,
            'vid' => 130,
            'action_type' => MikrotikSyncVidActionType::Created->value,
            'local_status_after' => VidStatus::Unknown->value,
        ]);
    }

    public function test_it_updates_unassigned_vids_and_marks_conflicts_for_active_services(): void
    {
        $router = $this->createRouter('RTR-SYNC-B');
        $scope = $this->createScope($router, 200, 299);
        $package = $this->createPackage();
        $customer = $this->createCustomer();

        $updatableVid = Vid::query()->create([
            'router_id' => $router->id,
            'scope_id' => $scope->id,
            'vid' => 210,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => '10.20.210.0/29',
            'gateway_ip' => '10.20.210.1',
            'pool_start_ip' => '10.20.210.2',
            'pool_end_ip' => '10.20.210.6',
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 10,
            'sync_source' => 'seed',
            'status' => VidStatus::Available->value,
        ]);

        $conflictedVid = Vid::query()->create([
            'router_id' => $router->id,
            'scope_id' => $scope->id,
            'vid' => 220,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => '10.20.220.0/29',
            'gateway_ip' => '10.20.220.1',
            'pool_start_ip' => '10.20.220.2',
            'pool_end_ip' => '10.20.220.6',
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 20,
            'sync_source' => 'seed',
            'status' => VidStatus::Assigned->value,
            'customer_id' => $customer->id,
        ]);

        $service = Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'olt_id' => null,
            'ont_id' => null,
            'vid_id' => $conflictedVid->id,
            'service_code' => 'SRV-SYNC-001',
            'monitor_vid' => 100,
            'monitor_pppoe_username' => 'pppoe-sync-001',
            'monitor_pppoe_password' => 'secret',
            'internet_vid' => 220,
            'subnet_cidr' => '10.20.220.0/29',
            'gateway_ip' => '10.20.220.1',
            'dhcp_pool_start' => '10.20.220.2',
            'dhcp_pool_end' => '10.20.220.6',
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'svc-sync-001',
            'billing_status' => ServiceBillingStatus::Paid->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => now()->toDateString(),
            'notes' => null,
        ]);

        $conflictedVid->update([
            'service_id' => $service->id,
        ]);

        config()->set('mikrotik.fake.routers.'.$router->router_code, [
            [
                'vid' => 210,
                'vlan_name' => 'cust-210',
                'subnet_cidr' => '10.20.210.8/29',
                'gateway_ip' => '10.20.210.9',
                'pool_start_ip' => '10.20.210.10',
                'pool_end_ip' => '10.20.210.14',
            ],
            [
                'vid' => 220,
                'vlan_name' => 'cust-220',
                'subnet_cidr' => '10.20.220.8/29',
                'gateway_ip' => '10.20.220.9',
                'pool_start_ip' => '10.20.220.10',
                'pool_end_ip' => '10.20.220.14',
            ],
        ]);

        $this->artisan('mikrotik:sync-vids', ['router' => $router->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('mikrotik_sync_jobs', [
            'router_id' => $router->id,
            'job_status' => MikrotikSyncJobStatus::Completed->value,
            'total_found' => 2,
            'total_new' => 0,
            'total_updated' => 1,
            'total_conflict' => 1,
        ]);

        $this->assertDatabaseHas('vids', [
            'id' => $updatableVid->id,
            'scope_id' => $scope->id,
            'subnet_cidr' => '10.20.210.8/29',
            'gateway_ip' => '10.20.210.9',
            'pool_start_ip' => '10.20.210.10',
            'pool_end_ip' => '10.20.210.14',
            'status' => VidStatus::Unregistered->value,
            'sync_source' => 'mikrotik:fake',
        ]);

        $this->assertDatabaseHas('vids', [
            'id' => $conflictedVid->id,
            'subnet_cidr' => '10.20.220.0/29',
            'gateway_ip' => '10.20.220.1',
            'pool_start_ip' => '10.20.220.2',
            'pool_end_ip' => '10.20.220.6',
            'status' => VidStatus::Assigned->value,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'sync_source' => 'mikrotik:fake',
        ]);

        $this->assertDatabaseHas('mikrotik_sync_vid_logs', [
            'router_id' => $router->id,
            'vid' => 210,
            'action_type' => MikrotikSyncVidActionType::Updated->value,
            'conflict_flag' => 0,
        ]);

        $this->assertDatabaseHas('mikrotik_sync_vid_logs', [
            'router_id' => $router->id,
            'vid' => 220,
            'action_type' => MikrotikSyncVidActionType::Conflict->value,
            'local_status_before' => VidStatus::Assigned->value,
            'local_status_after' => VidStatus::Assigned->value,
            'conflict_flag' => 1,
        ]);
    }

    public function test_it_classifies_vid_types_per_router_independently(): void
    {
        // Each router defines its own monitor_vid. The classification must be scoped
        // to that router's own scope — no VID value is globally reserved.

        // Router A: monitor_vid = 1000 (arbitrary choice for this router)
        $routerA = $this->createRouter('RTR-VIDTYPE-A');
        RouterScope::query()->create([
            'router_id' => $routerA->id,
            'scope_name' => 'Scope A',
            'monitor_vid' => 1000,
            'vid_start' => 1000,
            'vid_end' => 1099,
            'is_special' => false,
            'notes' => null,
        ]);
        config()->set('mikrotik.fake.routers.'.$routerA->router_code, [
            ['vid' => 1000, 'vlan_name' => 'V-1000-PPPOE', 'subnet_cidr' => null, 'gateway_ip' => null, 'pool_start_ip' => null, 'pool_end_ip' => null],
            ['vid' => 1001, 'vlan_name' => 'V-1001', 'subnet_cidr' => '10.102.1.0/24', 'gateway_ip' => '10.102.1.1', 'pool_start_ip' => '10.102.1.2', 'pool_end_ip' => '10.102.1.254'],
        ]);

        // Router B: monitor_vid = 2000 (completely different VID range)
        $routerB = $this->createRouter('RTR-VIDTYPE-B');
        RouterScope::query()->create([
            'router_id' => $routerB->id,
            'scope_name' => 'Scope B',
            'monitor_vid' => 2000,
            'vid_start' => 2000,
            'vid_end' => 2099,
            'is_special' => false,
            'notes' => null,
        ]);
        config()->set('mikrotik.fake.routers.'.$routerB->router_code, [
            ['vid' => 2000, 'vlan_name' => 'V-2000-PPPOE', 'subnet_cidr' => null, 'gateway_ip' => null, 'pool_start_ip' => null, 'pool_end_ip' => null],
            ['vid' => 2001, 'vlan_name' => 'V-2001', 'subnet_cidr' => '10.201.1.0/24', 'gateway_ip' => '10.201.1.1', 'pool_start_ip' => '10.201.1.2', 'pool_end_ip' => '10.201.1.254'],
        ]);

        $this->artisan('mikrotik:sync-vids', ['router' => $routerA->id])->assertSuccessful();
        $this->artisan('mikrotik:sync-vids', ['router' => $routerB->id])->assertSuccessful();

        // Router A: VID 1000 is monitor_vid for this router only → Monitoring
        $this->assertDatabaseHas('vids', ['router_id' => $routerA->id, 'vid' => 1000, 'vid_type' => VidType::Monitoring->value]);
        $this->assertDatabaseMissing('vids', ['router_id' => $routerA->id, 'vid' => 1000, 'vid_type' => VidType::CustomerInternet->value]);

        // Router A: VID 1001 is a customer VLAN → CustomerInternet
        $this->assertDatabaseHas('vids', [
            'router_id' => $routerA->id,
            'vid' => 1001,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => '10.102.1.0/24',
            'gateway_ip' => '10.102.1.1',
        ]);
        $this->assertDatabaseMissing('vids', ['router_id' => $routerA->id, 'vid' => 1001, 'subnet_cidr' => '10.102.1.1/24']);

        // Router B: VID 2000 is monitor_vid for this router only → Monitoring
        $this->assertDatabaseHas('vids', ['router_id' => $routerB->id, 'vid' => 2000, 'vid_type' => VidType::Monitoring->value]);
        $this->assertDatabaseMissing('vids', ['router_id' => $routerB->id, 'vid' => 2000, 'vid_type' => VidType::CustomerInternet->value]);

        // Router B: VID 2001 is a customer VLAN → CustomerInternet
        $this->assertDatabaseHas('vids', [
            'router_id' => $routerB->id,
            'vid' => 2001,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => '10.201.1.0/24',
            'gateway_ip' => '10.201.1.1',
        ]);
        $this->assertDatabaseMissing('vids', ['router_id' => $routerB->id, 'vid' => 2001, 'subnet_cidr' => '10.201.1.1/24']);

        // Cross-check: each router's VIDs are completely isolated from the other router
        $this->assertDatabaseMissing('vids', ['router_id' => $routerA->id, 'vid' => 2000]);
        $this->assertDatabaseMissing('vids', ['router_id' => $routerA->id, 'vid' => 2001]);
        $this->assertDatabaseMissing('vids', ['router_id' => $routerB->id, 'vid' => 1000]);
        $this->assertDatabaseMissing('vids', ['router_id' => $routerB->id, 'vid' => 1001]);
    }

    private function createRouter(string $routerCode): Router
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

    private function createScope(Router $router, int $vidStart, int $vidEnd): RouterScope
    {
        return RouterScope::query()->create([
            'router_id' => $router->id,
            'scope_name' => 'Sync Scope',
            'monitor_vid' => 100,
            'vid_start' => $vidStart,
            'vid_end' => $vidEnd,
            'is_special' => false,
            'notes' => null,
        ]);
    }

    private function createPackage(): Package
    {
        return Package::query()->create([
            'package_name' => 'Starter 20 Mbps',
            'monthly_price' => 350000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'description' => 'Sync test package',
            'is_active' => true,
        ]);
    }

    private function createCustomer(): Customer
    {
        return Customer::query()->create([
            'customer_code' => 'CUST-SYNC-001',
            'full_name' => 'Customer Sync',
            'phone' => '08123456789',
            'address' => 'Jl. Sinkronisasi No. 1',
            'customer_type' => CustomerType::Business->value,
            'status' => CustomerStatus::Active->value,
        ]);
    }
}
