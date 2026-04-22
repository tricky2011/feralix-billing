<?php

namespace Tests\Feature\Console;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceIsolationStatus;
use App\Enums\ServiceIsolationType;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Jobs\CheckOverdueInvoicesJob;
use App\Jobs\CreateOverdueServiceIsolationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Router;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BillingAutomationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-22 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_checks_overdue_invoices_and_creates_isolation_records(): void
    {
        $overdueService = $this->createService('SVC-OD-001', 120000);
        $pendingService = $this->createService('SVC-PN-001', 130000);
        $paidService = $this->createService('SVC-PD-001', 140000);

        $overdueInvoice = $this->createInvoice($overdueService, 120000, '2026-04-10', InvoicePaymentStatus::Unpaid);
        $this->createInvoice($pendingService, 130000, '2026-04-30', InvoicePaymentStatus::Unpaid);
        $this->createInvoice($paidService, 140000, '2026-04-20', InvoicePaymentStatus::Paid);

        $this->artisan('billing:check-overdue-invoices')
            ->assertSuccessful()
            ->expectsOutputToContain('overdue invoices 1');

        $this->assertDatabaseHas('invoices', [
            'id' => $overdueInvoice->id,
            'payment_status' => InvoicePaymentStatus::Overdue->value,
        ]);

        $this->assertDatabaseHas('services', [
            'id' => $overdueService->id,
            'billing_status' => ServiceBillingStatus::Overdue->value,
        ]);

        $this->assertDatabaseHas('services', [
            'id' => $pendingService->id,
            'billing_status' => ServiceBillingStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('services', [
            'id' => $paidService->id,
            'billing_status' => ServiceBillingStatus::Paid->value,
        ]);

        $this->artisan('billing:create-overdue-isolations')
            ->assertSuccessful()
            ->expectsOutputToContain('created 1 isolation(s)');

        $this->assertDatabaseHas('service_isolations', [
            'service_id' => $overdueService->id,
            'invoice_id' => $overdueInvoice->id,
            'isolation_type' => ServiceIsolationType::InvoiceOverdue->value,
            'status' => ServiceIsolationStatus::Pending->value,
        ]);

        $this->artisan('billing:create-overdue-isolations')
            ->assertSuccessful();

        $this->assertDatabaseCount('service_isolations', 1);
    }

    public function test_it_queues_overdue_automation_commands_when_requested(): void
    {
        Queue::fake();

        $this->artisan('billing:check-overdue-invoices', [
            '--queue' => true,
        ])->assertSuccessful();

        $this->artisan('billing:create-overdue-isolations', [
            '--queue' => true,
        ])->assertSuccessful();

        Queue::assertPushed(CheckOverdueInvoicesJob::class, 1);
        Queue::assertPushed(CreateOverdueServiceIsolationJob::class, 1);
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
            'mgmt_ip' => '10.10.20.'.random_int(10, 200),
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
            'internet_vid' => random_int(200, 350),
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

    private function createInvoice(
        Service $service,
        int $totalAmount,
        string $dueDate,
        InvoicePaymentStatus $paymentStatus,
    ): Invoice {
        return Invoice::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'invoice_number' => 'INV-'.str_pad((string) $service->id, 6, '0', STR_PAD_LEFT).'-'.str_replace('-', '', $dueDate),
            'billing_period' => '2026-04',
            'invoice_date' => '2026-04-01',
            'due_date' => $dueDate,
            'subtotal' => number_format($totalAmount, 2, '.', ''),
            'penalty_amount' => '0.00',
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'payment_status' => $paymentStatus->value,
        ]);
    }
}
