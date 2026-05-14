<?php

namespace Tests\Unit\Services\Billing;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\Service;
use App\Services\Billing\MonthlyInvoiceGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonthlyInvoiceGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    private MonthlyInvoiceGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MonthlyInvoiceGenerationService::class);
        Carbon::setTestNow('2026-05-14 00:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_due_date_on_day_28_for_february_28_days(): void
    {
        // Install tgl 28 -> billing Februari 2025 (28 hari) -> due tgl 28
        $this->createServiceWithInstallDate('2024-01-28');

        $payload = [
            'billing_period' => '2025-02',
            'invoice_date' => '2025-02-01',
        ];

        $result = $this->service->generate($payload);

        $dueDate = $result['generated'][0]->due_date;
        $this->assertEquals('2025-02-28', is_string($dueDate) ? $dueDate : (string) $dueDate);
    }

    public function test_due_date_on_day_29_for_february_28_days_clamped_to_28(): void
    {
        // Install tgl 29 -> billing Februari 2025 (28 hari) -> due tgl 28
        $this->createServiceWithInstallDate('2024-01-29');

        $payload = [
            'billing_period' => '2025-02',
            'invoice_date' => '2025-02-01',
        ];

        $result = $this->service->generate($payload);

        $dueDate = $result['generated'][0]->due_date;
        $this->assertEquals('2025-02-28', is_string($dueDate) ? $dueDate : (string) $dueDate);
    }

    public function test_due_date_on_day_30_for_february_28_days_clamped_to_28(): void
    {
        // Install tgl 30 -> billing Februari 2025 (28 hari) -> due tgl 28
        $this->createServiceWithInstallDate('2024-01-30');

        $payload = [
            'billing_period' => '2025-02',
            'invoice_date' => '2025-02-01',
        ];

        $result = $this->service->generate($payload);

        $dueDate = $result['generated'][0]->due_date;
        $this->assertEquals('2025-02-28', is_string($dueDate) ? $dueDate : (string) $dueDate);
    }

    public function test_due_date_on_day_31_for_february_28_days_clamped_to_28(): void
    {
        // Install tgl 31 -> billing Februari 2025 (28 hari) -> due tgl 28
        $this->createServiceWithInstallDate('2024-01-31');

        $payload = [
            'billing_period' => '2025-02',
            'invoice_date' => '2025-02-01',
        ];

        $result = $this->service->generate($payload);

        $dueDate = $result['generated'][0]->due_date;
        $this->assertEquals('2025-02-28', is_string($dueDate) ? $dueDate : (string) $dueDate);
    }

    public function test_due_date_on_day_31_for_march_31_days_not_clamped(): void
    {
        // Install tgl 31 -> billing Maret (31 hari) -> due tgl 31
        $this->createServiceWithInstallDate('2024-01-31');

        $payload = [
            'billing_period' => '2026-03',
            'invoice_date' => '2026-03-01',
        ];

        $result = $this->service->generate($payload);

        $dueDate = $result['generated'][0]->due_date;
        $this->assertEquals('2026-03-31', is_string($dueDate) ? $dueDate : (string) $dueDate);
    }

    public function test_due_date_on_day_31_for_april_30_days_clamped_to_30(): void
    {
        // Install tgl 31 -> billing April (30 hari) -> due tgl 30
        $this->createServiceWithInstallDate('2024-01-31');

        $payload = [
            'billing_period' => '2026-04',
            'invoice_date' => '2026-04-01',
        ];

        $result = $this->service->generate($payload);

        $dueDate = $result['generated'][0]->due_date;
        $this->assertEquals('2026-04-30', is_string($dueDate) ? $dueDate : (string) $dueDate);
    }

    public function test_due_date_not_clamped_when_install_day_within_month_days(): void
    {
        // Install tgl 15 -> billing Mei (31 hari) -> due tgl 15
        $this->createServiceWithInstallDate('2024-01-15');

        $payload = [
            'billing_period' => '2026-05',
            'invoice_date' => '2026-05-01',
        ];

        $result = $this->service->generate($payload);

        $dueDate = $result['generated'][0]->due_date;
        $this->assertEquals('2026-05-15', is_string($dueDate) ? $dueDate : (string) $dueDate);
    }

    public function test_due_date_adds_month_when_before_invoice_date(): void
    {
        // Install tgl 1 -> billing Mei 2026, invoice tgl 14 Mei -> due tgl 1 Juni (bulan berikutnya)
        $this->createServiceWithInstallDate('2024-01-01');

        $payload = [
            'billing_period' => '2026-05',
            'invoice_date' => '2026-05-14',
        ];

        $result = $this->service->generate($payload);

        $dueDate = $result['generated'][0]->due_date;
        $this->assertEquals('2026-06-01', is_string($dueDate) ? $dueDate : (string) $dueDate);
    }

    private function createServiceWithInstallDate(string $installDate): Service
    {
        $customer = Customer::query()->create([
            'customer_code' => 'CUST-'.uniqid(),
            'full_name' => 'Customer '.uniqid(),
            'phone' => '081234567890',
            'address' => 'Jl. Test '.uniqid(),
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
            'install_date' => $installDate,
        ]);

        $package = Package::query()->create([
            'package_name' => 'Package '.uniqid(),
            'monthly_price' => 150000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        $router = Router::query()->create([
            'router_code' => 'RTR-'.uniqid(),
            'router_name' => 'Router '.uniqid(),
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.10.10',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_active' => true,
        ]);

        return Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'service_code' => 'SVC-'.uniqid(),
            'monitor_vid' => 100,
            'monitor_pppoe_username' => 'monitor.'.uniqid(),
            'monitor_pppoe_password' => 'secret-monitor',
            'internet_vid' => 210,
            'subnet_cidr' => '10.0.0.0/24',
            'gateway_ip' => '10.0.0.1',
            'dhcp_pool_start' => '10.0.0.10',
            'dhcp_pool_end' => '10.0.0.50',
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'FTTH-'.uniqid(),
            'billing_status' => ServiceBillingStatus::Pending->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => $installDate,
        ]);
    }
}