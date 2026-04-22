<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\VidStatus;
use App\Enums\VidType;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Olt;
use App\Models\Package;
use App\Models\Router;
use App\Models\RouterScope;
use App\Models\Service;
use App\Models\Technician;
use App\Models\Vid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerManagementEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-22 10:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_onboards_customer_with_initial_service_work_order_and_invoice(): void
    {
        $location = $this->createLocation('LOC-A');
        $olt = $this->createOlt('A', $location);
        $technician = $this->createTechnician('TECH-CUST-01', 'Teknisi Onboarding');
        $package = $this->createPackage('Starter 30 Mbps', 150000);
        $router = $this->createRouter('A');
        $scope = $this->createRouterScope($router, 150);
        $vid = $this->createVid($router, $scope, 310, '10.20.30.0/29', '10.20.30.1', '10.20.30.2', '10.20.30.6');

        $response = $this->postJson('/api/v1/admin/customers/onboard', [
            'customer_code' => 'CUST-ONB-001',
            'full_name' => 'Pelanggan Onboarding',
            'phone' => '081234567890',
            'address' => 'Jl. Fiber No. 1',
            'location_id' => $location->id,
            'preferred_olt_id' => $olt->id,
            'assigned_technician_id' => $technician->id,
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
            'service' => [
                'package_id' => $package->id,
                'olt_id' => $olt->id,
                'vid_id' => $vid->id,
                'service_code' => 'SVC-ONB-001',
            ],
            'create_initial_invoice' => true,
            'initial_invoice' => [
                'billing_period' => '2026-04',
                'invoice_date' => '2026-04-22',
                'due_date' => '2026-04-30',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.customer.customer_code', 'CUST-ONB-001')
            ->assertJsonPath('data.customer.location.id', $location->id)
            ->assertJsonPath('data.customer.assigned_technician.id', $technician->id)
            ->assertJsonPath('data.service.service_code', 'SVC-ONB-001')
            ->assertJsonPath('data.service.router_id', $router->id)
            ->assertJsonPath('data.service.olt_id', $olt->id)
            ->assertJsonPath('data.service.internet_vid', 310)
            ->assertJsonPath('data.service.subnet_cidr', '10.20.30.0/29')
            ->assertJsonPath('data.service.dhcp_pool_start', '10.20.30.2')
            ->assertJsonPath('data.work_order.assigned_technician_id', $technician->id)
            ->assertJsonPath('data.initial_invoice.billing_period', '2026-04')
            ->assertJsonPath('data.initial_invoice.payment_status', InvoicePaymentStatus::Unpaid->value);

        $this->assertDatabaseHas('customers', [
            'customer_code' => 'CUST-ONB-001',
            'location_id' => $location->id,
            'preferred_olt_id' => $olt->id,
            'assigned_technician_id' => $technician->id,
        ]);

        $this->assertDatabaseHas('services', [
            'customer_id' => $response->json('data.customer.id'),
            'service_code' => 'SVC-ONB-001',
            'router_id' => $router->id,
            'olt_id' => $olt->id,
            'vid_id' => $vid->id,
            'internet_vid' => 310,
        ]);

        $this->assertDatabaseHas('work_orders', [
            'customer_id' => $response->json('data.customer.id'),
            'assigned_technician_id' => $technician->id,
        ]);

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $response->json('data.customer.id'),
            'billing_period' => '2026-04',
        ]);
    }

    public function test_it_filters_customers_by_router_location_and_olt_and_previews_remote_ip(): void
    {
        $locationA = $this->createLocation('LOC-BDG');
        $locationB = $this->createLocation('LOC-JKT');
        $oltA = $this->createOlt('BDG', $locationA);
        $oltB = $this->createOlt('JKT', $locationB);
        $technician = $this->createTechnician('TECH-CUST-02', 'Teknisi Filter');
        $package = $this->createPackage('Business 50 Mbps', 250000);
        $routerA = $this->createRouter('BDG');
        $routerB = $this->createRouter('JKT');
        $scopeA = $this->createRouterScope($routerA, 120);
        $scopeB = $this->createRouterScope($routerB, 130);
        $vidA = $this->createVid($routerA, $scopeA, 320, '10.30.40.0/29', '10.30.40.1', '10.30.40.2', '10.30.40.6');
        $previewVid = $this->createVid($routerA, $scopeA, 321, '10.30.41.0/29', '10.30.41.1', '10.30.41.2', '10.30.41.6');
        $vidB = $this->createVid($routerB, $scopeB, 330, '10.30.50.0/29', '10.30.50.1', '10.30.50.2', '10.30.50.6');

        $customerA = $this->createCustomer('CUST-FLTR-001', $locationA, $oltA, $technician);
        $customerB = $this->createCustomer('CUST-FLTR-002', $locationB, $oltB, $technician);

        $this->createService($customerA, $package, $routerA, $oltA, $vidA, 'SVC-FLTR-001');
        $this->createService($customerB, $package, $routerB, $oltB, $vidB, 'SVC-FLTR-002');

        $response = $this->getJson(sprintf(
            '/api/v1/admin/customers?router_id=%d&location_id=%d&olt_id=%d&search=%s',
            $routerA->id,
            $locationA->id,
            $oltA->id,
            'RTR-BDG'
        ));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer_code', 'CUST-FLTR-001')
            ->assertJsonPath('data.0.location.id', $locationA->id)
            ->assertJsonPath('data.0.latest_active_service.router.id', $routerA->id)
            ->assertJsonPath('data.0.latest_active_service.olt.id', $oltA->id);

        $previewResponse = $this->postJson('/api/v1/admin/customers/provisioning-preview', [
            'vid_id' => $previewVid->id,
        ]);

        $previewResponse
            ->assertOk()
            ->assertJsonPath('data.source', 'vid')
            ->assertJsonPath('data.router_id', $routerA->id)
            ->assertJsonPath('data.internet_vid', 321)
            ->assertJsonPath('data.subnet_cidr', '10.30.41.0/29')
            ->assertJsonPath('data.remote_ip_suggestion', '10.30.41.2');
    }

    public function test_it_processes_bulk_disable_invoice_generation_and_safe_bulk_delete(): void
    {
        $location = $this->createLocation('LOC-C');
        $olt = $this->createOlt('C', $location);
        $technician = $this->createTechnician('TECH-CUST-03', 'Teknisi Bulk');
        $package = $this->createPackage('Bulk Package', 175000);
        $router = $this->createRouter('C');
        $scope = $this->createRouterScope($router, 140);
        $vid = $this->createVid($router, $scope, 340, '10.40.60.0/29', '10.40.60.1', '10.40.60.2', '10.40.60.6');

        $deletableCustomer = $this->createCustomer('CUST-BULK-001', $location, $olt, $technician);
        $billableCustomer = $this->createCustomer('CUST-BULK-002', $location, $olt, $technician);
        $secondDeletableCustomer = $this->createCustomer('CUST-BULK-003', $location, $olt, $technician);

        $this->createService($billableCustomer, $package, $router, $olt, $vid, 'SVC-BULK-001');

        $disableResponse = $this->postJson('/api/v1/admin/customers/bulk-disable', [
            'customer_ids' => [
                $deletableCustomer->id,
                $billableCustomer->id,
            ],
        ]);

        $disableResponse
            ->assertOk()
            ->assertJsonPath('data.disabled_count', 2);

        $this->assertDatabaseHas('customers', [
            'id' => $deletableCustomer->id,
            'status' => CustomerStatus::Inactive->value,
        ]);
        $this->assertDatabaseHas('customers', [
            'id' => $billableCustomer->id,
            'status' => CustomerStatus::Inactive->value,
        ]);

        $invoiceResponse = $this->postJson('/api/v1/admin/customers/bulk-generate-invoice', [
            'customer_ids' => [
                $billableCustomer->id,
                $secondDeletableCustomer->id,
            ],
            'billing_period' => '2026-04',
            'invoice_date' => '2026-04-22',
            'due_date' => '2026-04-30',
        ]);

        $invoiceResponse
            ->assertOk()
            ->assertJsonPath('data.generated_count', 1)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.generated.0.customer_id', $billableCustomer->id);

        $deleteResponse = $this->postJson('/api/v1/admin/customers/bulk-delete', [
            'customer_ids' => [
                $deletableCustomer->id,
                $billableCustomer->id,
                $secondDeletableCustomer->id,
            ],
        ]);

        $deleteResponse
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 2)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.skipped.0.customer_id', $billableCustomer->id);

        $this->assertDatabaseMissing('customers', [
            'id' => $deletableCustomer->id,
        ]);
        $this->assertDatabaseHas('customers', [
            'id' => $billableCustomer->id,
        ]);
        $this->assertDatabaseMissing('customers', [
            'id' => $secondDeletableCustomer->id,
        ]);
    }

    private function createLocation(string $suffix): Location
    {
        return Location::query()->create([
            'location_code' => $suffix,
            'location_name' => 'Location '.$suffix,
            'address' => 'Jl. '.$suffix,
            'is_active' => true,
        ]);
    }

    private function createOlt(string $suffix, Location $location): Olt
    {
        return Olt::query()->create([
            'olt_code' => 'OLT-'.$suffix,
            'olt_name' => 'OLT '.$suffix,
            'mgmt_ip' => '172.16.'.strlen($suffix).'.1',
            'vendor_name' => 'Huawei',
            'location_id' => $location->id,
            'location_name' => $location->location_name,
            'is_active' => true,
        ]);
    }

    private function createTechnician(string $code, string $name): Technician
    {
        return Technician::query()->create([
            'technician_code' => $code,
            'full_name' => $name,
            'phone' => '081200000000',
            'is_active' => true,
        ]);
    }

    private function createPackage(string $name, int $monthlyPrice): Package
    {
        return Package::query()->create([
            'package_name' => $name,
            'monthly_price' => $monthlyPrice,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 50,
            'is_active' => true,
        ]);
    }

    private function createRouter(string $suffix): Router
    {
        return Router::query()->create([
            'router_code' => 'RTR-'.$suffix,
            'router_name' => 'Router '.$suffix,
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.'.strlen($suffix).'.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'location_name' => 'Site '.$suffix,
            'is_active' => true,
        ]);
    }

    private function createRouterScope(Router $router, int $monitorVid): RouterScope
    {
        return RouterScope::query()->create([
            'router_id' => $router->id,
            'scope_name' => 'Scope '.$router->router_code,
            'monitor_vid' => $monitorVid,
            'vid_start' => 300,
            'vid_end' => 399,
            'is_special' => false,
        ]);
    }

    private function createVid(
        Router $router,
        RouterScope $scope,
        int $vidNumber,
        string $subnetCidr,
        string $gatewayIp,
        string $poolStart,
        string $poolEnd,
    ): Vid {
        return Vid::query()->create([
            'router_id' => $router->id,
            'scope_id' => $scope->id,
            'vid' => $vidNumber,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => $subnetCidr,
            'gateway_ip' => $gatewayIp,
            'pool_start_ip' => $poolStart,
            'pool_end_ip' => $poolEnd,
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 50,
            'sync_source' => 'test',
            'status' => VidStatus::Available->value,
        ]);
    }

    private function createCustomer(string $code, Location $location, Olt $olt, Technician $technician): Customer
    {
        return Customer::query()->create([
            'customer_code' => $code,
            'full_name' => 'Customer '.$code,
            'phone' => '081234567890',
            'address' => 'Alamat '.$code,
            'location_id' => $location->id,
            'preferred_olt_id' => $olt->id,
            'assigned_technician_id' => $technician->id,
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);
    }

    private function createService(
        Customer $customer,
        Package $package,
        Router $router,
        Olt $olt,
        Vid $vid,
        string $serviceCode,
    ): Service {
        $service = Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'olt_id' => $olt->id,
            'vid_id' => $vid->id,
            'service_code' => $serviceCode,
            'monitor_vid' => 140,
            'monitor_pppoe_username' => strtolower($serviceCode).'.pppoe',
            'monitor_pppoe_password' => 'secret-monitor',
            'internet_vid' => $vid->vid,
            'subnet_cidr' => $vid->subnet_cidr,
            'gateway_ip' => $vid->gateway_ip,
            'dhcp_pool_start' => $vid->pool_start_ip,
            'dhcp_pool_end' => $vid->pool_end_ip,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 50,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'ADDR-'.$serviceCode,
            'billing_status' => ServiceBillingStatus::Pending->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-01',
        ]);

        $vid->update([
            'status' => VidStatus::Assigned->value,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
        ]);

        return $service;
    }
}
