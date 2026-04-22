<?php

namespace Tests\Feature\Console;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Jobs\GenerateMonthlyInvoicesJob;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateMonthlyInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-01 00:05:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_generates_monthly_invoices_inline(): void
    {
        $service = $this->createService('SVC-GEN-001', 150000);

        $this->artisan('billing:generate-monthly-invoices', [
            'billing_period' => '2026-05',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('generated 1 invoice(s), skipped 0');

        $this->assertDatabaseHas('invoices', [
            'service_id' => $service->id,
            'billing_period' => '2026-05',
            'invoice_date' => '2026-05-01',
            'due_date' => '2026-05-11',
            'subtotal' => '150000.00',
            'penalty_amount' => '0.00',
            'total_amount' => '150000.00',
            'payment_status' => InvoicePaymentStatus::Issued->value,
        ]);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'billing_status' => ServiceBillingStatus::Pending->value,
        ]);
    }

    public function test_it_queues_monthly_invoice_generation_when_requested(): void
    {
        Queue::fake();

        $this->artisan('billing:generate-monthly-invoices', [
            'billing_period' => '2026-06',
            '--queue' => true,
        ])->assertSuccessful();

        Queue::assertPushed(GenerateMonthlyInvoicesJob::class, 1);
    }

    private function createService(string $serviceCode, int $monthlyPrice): Service
    {
        $customer = Customer::query()->create([
            'customer_code' => 'CUST-'.$serviceCode,
            'full_name' => 'Customer '.$serviceCode,
            'phone' => '081234567890',
            'address' => 'Jl. Billing '.$serviceCode,
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);

        $package = Package::query()->create([
            'package_name' => 'Package '.$serviceCode,
            'monthly_price' => $monthlyPrice,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        $router = Router::query()->create([
            'router_code' => 'RTR-'.$serviceCode,
            'router_name' => 'Router '.$serviceCode,
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
            'service_code' => $serviceCode,
            'monitor_vid' => 100,
            'monitor_pppoe_username' => strtolower('monitor.'.$serviceCode),
            'monitor_pppoe_password' => 'secret-monitor',
            'internet_vid' => 210,
            'subnet_cidr' => '10.0.0.0/24',
            'gateway_ip' => '10.0.0.1',
            'dhcp_pool_start' => '10.0.0.10',
            'dhcp_pool_end' => '10.0.0.50',
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
