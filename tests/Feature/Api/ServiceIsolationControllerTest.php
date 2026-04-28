<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceIsolationStatus;
use App\Enums\ServiceIsolationType;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Jobs\ProcessServiceRouterOperationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceIsolation;
use App\Models\Vid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServiceIsolationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-22 09:00:00');
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_a_pending_manual_isolation_record_with_default_values(): void
    {
        $service = $this->createService(serviceCode: 'SVC-ISO-001');

        $response = $this->postJson('/api/v1/admin/service-isolations', [
            'service_id' => $service->id,
            'notes' => 'Manual isolation requested by admin.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.router_id', $service->router_id)
            ->assertJsonPath('data.isolation_type', ServiceIsolationType::Manual->value)
            ->assertJsonPath('data.address_list_name', config('mikrotik.isolation.address_list_name'))
            ->assertJsonPath('data.target_subnet', $service->subnet_cidr)
            ->assertJsonPath('data.redirect_url', config('billing.ftth_isolation.default_redirect_url'))
            ->assertJsonPath('data.status', ServiceIsolationStatus::Pending->value)
            ->assertJsonPath('data.notes', 'Manual isolation requested by admin.');

        $this->assertDatabaseHas('service_isolations', [
            'service_id' => $service->id,
            'router_id' => $service->router_id,
            'invoice_id' => null,
            'isolation_type' => ServiceIsolationType::Manual->value,
            'address_list_name' => config('mikrotik.isolation.address_list_name'),
            'target_subnet' => $service->subnet_cidr,
            'status' => ServiceIsolationStatus::Pending->value,
        ]);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 1);

        $service->refresh();

        $this->assertSame(ServiceOverallStatus::Active, $service->overall_status);
        $this->assertSame(ServiceNetworkStatus::Active, $service->network_status);
    }

    public function test_it_lists_service_isolation_history_with_standard_pagination(): void
    {
        $firstService = $this->createService(serviceCode: 'SVC-ISO-LIST-1');
        $secondService = $this->createService(serviceCode: 'SVC-ISO-LIST-2');

        ServiceIsolation::query()->create([
            'service_id' => $firstService->id,
            'router_id' => $firstService->router_id,
            'isolation_type' => ServiceIsolationType::Manual->value,
            'address_list_name' => 'isolir',
            'target_subnet' => $firstService->subnet_cidr,
            'status' => ServiceIsolationStatus::Pending->value,
        ]);

        ServiceIsolation::query()->create([
            'service_id' => $secondService->id,
            'router_id' => $secondService->router_id,
            'isolation_type' => ServiceIsolationType::Manual->value,
            'address_list_name' => 'isolir',
            'target_subnet' => $secondService->subnet_cidr,
            'status' => ServiceIsolationStatus::Released->value,
            'released_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/service-isolations?per_page=1');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonMissingPath('data.0.service.monitor_pppoe_password')
            ->assertJsonMissingPath('data.0.router.api_password');
    }

    public function test_it_filters_service_isolation_history(): void
    {
        $firstService = $this->createService(serviceCode: 'SVC-ISO-FILTER-1');
        $secondService = $this->createService(serviceCode: 'SVC-ISO-FILTER-2');
        $invoice = $this->createInvoice($firstService, dueDate: '2026-04-15');

        ServiceIsolation::query()->create([
            'service_id' => $firstService->id,
            'router_id' => $firstService->router_id,
            'invoice_id' => $invoice->id,
            'isolation_type' => ServiceIsolationType::InvoiceOverdue->value,
            'address_list_name' => 'isolir',
            'target_subnet' => $firstService->subnet_cidr,
            'status' => ServiceIsolationStatus::Pending->value,
        ]);

        ServiceIsolation::query()->create([
            'service_id' => $secondService->id,
            'router_id' => $secondService->router_id,
            'isolation_type' => ServiceIsolationType::Manual->value,
            'address_list_name' => 'isolir',
            'target_subnet' => $secondService->subnet_cidr,
            'status' => ServiceIsolationStatus::Released->value,
            'released_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/service-isolations?'.http_build_query([
            'status' => ServiceIsolationStatus::Pending->value,
            'router_id' => $firstService->router_id,
            'service_id' => $firstService->id,
            'customer_id' => $firstService->customer_id,
            'invoice_id' => $invoice->id,
            'date_from' => '2026-04-22',
            'date_to' => '2026-04-22',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.service_id', $firstService->id)
            ->assertJsonPath('data.0.invoice_id', $invoice->id)
            ->assertJsonPath('data.0.status', ServiceIsolationStatus::Pending->value);
    }

    public function test_it_creates_an_overdue_invoice_isolation_record_when_invoice_is_overdue(): void
    {
        $service = $this->createService(serviceCode: 'SVC-ISO-OD');
        $invoice = $this->createInvoice($service, dueDate: '2026-04-15');

        $response = $this->postJson('/api/v1/admin/service-isolations', [
            'service_id' => $service->id,
            'invoice_id' => $invoice->id,
            'isolation_type' => ServiceIsolationType::InvoiceOverdue->value,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.invoice_id', $invoice->id)
            ->assertJsonPath('data.isolation_type', ServiceIsolationType::InvoiceOverdue->value)
            ->assertJsonPath('data.status', ServiceIsolationStatus::Pending->value);

        $this->assertDatabaseHas('service_isolations', [
            'service_id' => $service->id,
            'invoice_id' => $invoice->id,
            'isolation_type' => ServiceIsolationType::InvoiceOverdue->value,
            'status' => ServiceIsolationStatus::Pending->value,
        ]);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 1);
    }

    public function test_it_rejects_a_new_open_isolation_when_service_already_has_one(): void
    {
        $service = $this->createService(serviceCode: 'SVC-ISO-DUP');

        ServiceIsolation::query()->create([
            'service_id' => $service->id,
            'router_id' => $service->router_id,
            'isolation_type' => ServiceIsolationType::Manual->value,
            'address_list_name' => config('mikrotik.isolation.address_list_name'),
            'target_subnet' => $service->subnet_cidr,
            'redirect_url' => config('billing.ftth_isolation.default_redirect_url'),
            'status' => ServiceIsolationStatus::Pending->value,
        ]);

        $response = $this->postJson('/api/v1/admin/service-isolations', [
            'service_id' => $service->id,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id']);

        Queue::assertNothingPushed();
    }

    public function test_it_marks_an_isolation_as_applied_and_releases_it_manually(): void
    {
        $service = $this->createService(serviceCode: 'SVC-ISO-LC');

        $isolation = ServiceIsolation::query()->create([
            'service_id' => $service->id,
            'router_id' => $service->router_id,
            'isolation_type' => ServiceIsolationType::Manual->value,
            'address_list_name' => config('mikrotik.isolation.address_list_name'),
            'target_subnet' => $service->subnet_cidr,
            'redirect_url' => config('billing.ftth_isolation.default_redirect_url'),
            'status' => ServiceIsolationStatus::Pending->value,
        ]);

        $appliedResponse = $this->patchJson("/api/v1/admin/service-isolations/{$isolation->id}/applied", [
            'isolated_at' => '2026-04-22 09:30:00',
            'notes' => 'Address-list applied manually on router.',
        ]);

        $appliedResponse
            ->assertOk()
            ->assertJsonPath('data.status', ServiceIsolationStatus::Applied->value)
            ->assertJsonPath('data.isolated_at', '2026-04-22T09:30:00.000000Z');

        $service->refresh();

        $this->assertSame(ServiceOverallStatus::Isolated, $service->overall_status);
        $this->assertSame(ServiceNetworkStatus::Isolated, $service->network_status);

        $releaseResponse = $this->patchJson("/api/v1/admin/service-isolations/{$isolation->id}/release", [
            'released_at' => '2026-04-22 10:00:00',
            'notes' => 'Isolation released after manual verification.',
        ]);

        $releaseResponse
            ->assertOk()
            ->assertJsonPath('data.status', ServiceIsolationStatus::Released->value)
            ->assertJsonPath('data.released_at', '2026-04-22T10:00:00.000000Z');

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 1);

        $service->refresh();

        $this->assertSame(ServiceOverallStatus::Active, $service->overall_status);
        $this->assertSame(ServiceNetworkStatus::Active, $service->network_status);

        $this->assertDatabaseHas('service_isolations', [
            'id' => $isolation->id,
            'status' => ServiceIsolationStatus::Released->value,
            'released_at' => '2026-04-22 10:00:00',
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
            'mgmt_ip' => '10.10.10.'.random_int(10, 200),
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

    private function createInvoice(Service $service, string $dueDate): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'invoice_number' => 'INV-'.str_pad((string) $service->id, 6, '0', STR_PAD_LEFT).'-'.str_replace('-', '', $dueDate),
            'billing_period' => '2026-04',
            'invoice_date' => '2026-04-01',
            'due_date' => $dueDate,
            'subtotal' => '200000.00',
            'penalty_amount' => '0.00',
            'total_amount' => '200000.00',
            'payment_status' => InvoicePaymentStatus::Unpaid->value,
        ]);
    }
}
