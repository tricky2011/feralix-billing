<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Jobs\ProcessServiceRouterOperationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-21 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_generates_monthly_invoices_for_billable_services(): void
    {
        $activeService = $this->createService(
            serviceCode: 'SVC-ACTIVE-001',
            monthlyPrice: 150000,
            overallStatus: ServiceOverallStatus::Active,
            networkStatus: ServiceNetworkStatus::Active
        );

        $downService = $this->createService(
            serviceCode: 'SVC-DOWN-001',
            monthlyPrice: 175000,
            overallStatus: ServiceOverallStatus::Down,
            networkStatus: ServiceNetworkStatus::Active
        );

        $inactiveService = $this->createService(
            serviceCode: 'SVC-INACTIVE-001',
            monthlyPrice: 220000,
            overallStatus: ServiceOverallStatus::Inactive,
            networkStatus: ServiceNetworkStatus::Inactive
        );

        $response = $this->postJson('/api/v1/admin/invoices/generate-monthly', [
            'billing_period' => '2026-04',
            'invoice_date' => '2026-04-21',
            'due_date' => '2026-04-30',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.billing_period', '2026-04')
            ->assertJsonPath('meta.generated_count', 2)
            ->assertJsonPath('meta.skipped_count', 0)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.service_id', $activeService->id)
            ->assertJsonPath('data.0.payment_status', InvoicePaymentStatus::Issued->value)
            ->assertJsonPath('data.0.subtotal', '150000.00')
            ->assertJsonPath('data.0.total_amount', '150000.00');

        $this->assertDatabaseHas('invoices', [
            'service_id' => $activeService->id,
            'customer_id' => $activeService->customer_id,
            'billing_period' => '2026-04',
            'subtotal' => '150000.00',
            'penalty_amount' => '0.00',
            'total_amount' => '150000.00',
            'payment_status' => InvoicePaymentStatus::Issued->value,
        ]);

        $this->assertDatabaseHas('invoices', [
            'service_id' => $downService->id,
            'customer_id' => $downService->customer_id,
            'billing_period' => '2026-04',
            'subtotal' => '175000.00',
            'penalty_amount' => '0.00',
            'total_amount' => '175000.00',
            'payment_status' => InvoicePaymentStatus::Issued->value,
        ]);

        $this->assertDatabaseMissing('invoices', [
            'billing_period' => '2026-04',
            'service_id' => $inactiveService->id,
        ]);
    }

    public function test_it_records_partial_and_full_payments_and_updates_invoice_status_and_cashflow(): void
    {
        $service = $this->createService(
            serviceCode: 'SVC-BILL-001',
            monthlyPrice: 200000,
            overallStatus: ServiceOverallStatus::Active,
            networkStatus: ServiceNetworkStatus::Active
        );

        $invoice = $this->createInvoice($service, totalAmount: 200000, dueDate: '2026-04-30');

        $firstPaymentResponse = $this->postJson('/api/v1/admin/payments', [
            'invoice_id' => $invoice->id,
            'amount_paid' => 50000,
            'payment_method' => 'cash',
            'paid_at' => '2026-04-21 10:00:00',
            'reference_no' => 'PAY-001',
        ]);

        $firstPaymentResponse
            ->assertCreated()
            ->assertJsonPath('data.invoice_id', $invoice->id)
            ->assertJsonPath('data.customer_id', $service->customer_id)
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.amount_paid', '50000.00')
            ->assertJsonPath('data.invoice.payment_status', InvoicePaymentStatus::PartiallyPaid->value)
            ->assertJsonPath('data.invoice.remaining_amount', '150000.00')
            ->assertJsonPath('data.cashflow_transaction.direction', 'income')
            ->assertJsonPath('data.cashflow_transaction.category', 'invoice_payment');

        $invoice->refresh();
        $service->refresh();

        $this->assertSame(InvoicePaymentStatus::PartiallyPaid, $invoice->payment_status);
        $this->assertSame(ServiceBillingStatus::Pending, $service->billing_status);

        $secondPaymentResponse = $this->postJson('/api/v1/admin/payments', [
            'invoice_id' => $invoice->id,
            'amount_paid' => 150000,
            'payment_method' => 'bank_transfer',
            'paid_at' => '2026-04-21 11:00:00',
            'reference_no' => 'PAY-002',
        ]);

        $secondPaymentResponse
            ->assertCreated()
            ->assertJsonPath('data.invoice.payment_status', InvoicePaymentStatus::Paid->value)
            ->assertJsonPath('data.invoice.remaining_amount', '0.00');

        $invoice->refresh();
        $service->refresh();

        $this->assertSame(InvoicePaymentStatus::Paid, $invoice->payment_status);
        $this->assertSame(ServiceBillingStatus::Paid, $service->billing_status);

        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('cashflow_transactions', 2);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'amount_paid' => '150000.00',
            'payment_method' => 'bank_transfer',
        ]);
        $this->assertDatabaseHas('cashflow_transactions', [
            'payment_id' => Payment::query()->latest('id')->value('id'),
            'invoice_id' => $invoice->id,
            'direction' => 'income',
            'category' => 'invoice_payment',
        ]);
    }

    public function test_it_rejects_overpayment_for_an_invoice(): void
    {
        $service = $this->createService(
            serviceCode: 'SVC-BILL-002',
            monthlyPrice: 100000,
            overallStatus: ServiceOverallStatus::Active,
            networkStatus: ServiceNetworkStatus::Active
        );

        $invoice = $this->createInvoice($service, totalAmount: 100000, dueDate: '2026-04-30');

        $response = $this->postJson('/api/v1/admin/payments', [
            'invoice_id' => $invoice->id,
            'amount_paid' => 125000,
            'payment_method' => 'cash',
            'paid_at' => '2026-04-21 09:00:00',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_paid']);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(InvoicePaymentStatus::Unpaid, $invoice->refresh()->payment_status);
    }

    public function test_it_lists_overdue_and_unpaid_invoices(): void
    {
        $serviceA = $this->createService(
            serviceCode: 'SVC-OD-001',
            monthlyPrice: 120000,
            overallStatus: ServiceOverallStatus::Active,
            networkStatus: ServiceNetworkStatus::Active
        );

        $serviceB = $this->createService(
            serviceCode: 'SVC-UP-001',
            monthlyPrice: 130000,
            overallStatus: ServiceOverallStatus::Active,
            networkStatus: ServiceNetworkStatus::Active
        );

        $serviceC = $this->createService(
            serviceCode: 'SVC-PD-001',
            monthlyPrice: 140000,
            overallStatus: ServiceOverallStatus::Active,
            networkStatus: ServiceNetworkStatus::Active
        );

        $overdueInvoice = $this->createInvoice($serviceA, totalAmount: 120000, dueDate: '2026-04-10');
        $partialInvoice = $this->createInvoice(
            $serviceB,
            totalAmount: 130000,
            dueDate: '2026-04-25',
            paymentStatus: InvoicePaymentStatus::PartiallyPaid
        );
        $paidInvoice = $this->createInvoice(
            $serviceC,
            totalAmount: 140000,
            dueDate: '2026-04-12',
            paymentStatus: InvoicePaymentStatus::Paid
        );

        Payment::query()->create([
            'invoice_id' => $partialInvoice->id,
            'customer_id' => $serviceB->customer_id,
            'service_id' => $serviceB->id,
            'amount_paid' => '30000.00',
            'payment_method' => 'cash',
            'paid_at' => '2026-04-21 12:00:00',
        ]);

        Payment::query()->create([
            'invoice_id' => $paidInvoice->id,
            'customer_id' => $serviceC->customer_id,
            'service_id' => $serviceC->id,
            'amount_paid' => '140000.00',
            'payment_method' => 'cash',
            'paid_at' => '2026-04-20 12:00:00',
        ]);

        $overdueResponse = $this->getJson('/api/v1/admin/invoices/overdue');
        $unpaidResponse = $this->getJson('/api/v1/admin/invoices/unpaid');

        $overdueResponse
            ->assertOk()
            ->assertJsonPath('data.0.id', $overdueInvoice->id);

        $unpaidIds = collect($unpaidResponse->json('data'))->pluck('id')->all();

        $this->assertContains($overdueInvoice->id, $unpaidIds);
        $this->assertContains($partialInvoice->id, $unpaidIds);
        $this->assertNotContains($paidInvoice->id, $unpaidIds);
    }

    public function test_it_updates_returns_detail_and_archives_invoice_safely(): void
    {
        $service = $this->createService(
            serviceCode: 'SVC-UPD-001',
            monthlyPrice: 125000,
            overallStatus: ServiceOverallStatus::Active,
            networkStatus: ServiceNetworkStatus::Active
        );

        $invoice = $this->createInvoice($service, totalAmount: 125000, dueDate: '2026-04-30');

        $updateResponse = $this->putJson('/api/v1/admin/invoices/'.$invoice->id, [
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'invoice_number' => 'INV-UPD-001',
            'billing_period' => '2026-04',
            'invoice_date' => '2026-04-21',
            'due_date' => '2026-05-02',
            'subtotal' => 130000,
            'penalty_amount' => 5000,
            'issue_now' => true,
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-UPD-001')
            ->assertJsonPath('data.total_amount', '135000.00')
            ->assertJsonPath('data.payment_status', InvoicePaymentStatus::Issued->value)
            ->assertJsonPath('data.service.router.id', $service->router_id)
            ->assertJsonPath('data.lifecycle.can_delete', true);

        $detailResponse = $this->getJson('/api/v1/admin/invoices/'.$invoice->id);

        $detailResponse
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-UPD-001')
            ->assertJsonPath('data.service.router.id', $service->router_id);

        $deleteResponse = $this->deleteJson('/api/v1/admin/invoices/'.$invoice->id);

        $deleteResponse->assertOk();
        $this->assertSoftDeleted('invoices', [
            'id' => $invoice->id,
        ]);

        $paidInvoice = $this->createInvoice($service, totalAmount: 100000, dueDate: '2026-05-10', billingPeriod: '2026-05');
        $this->postJson('/api/v1/admin/payments', [
            'invoice_id' => $paidInvoice->id,
            'amount_paid' => 100000,
            'payment_method' => 'cash',
            'paid_at' => '2026-04-21 13:00:00',
        ])->assertCreated();

        $this->deleteJson('/api/v1/admin/invoices/'.$paidInvoice->id)
            ->assertUnprocessable();
    }

    public function test_it_processes_bulk_mark_paid_action_and_creates_cashflow(): void
    {
        $service = $this->createService(
            serviceCode: 'SVC-BULK-001',
            monthlyPrice: 145000,
            overallStatus: ServiceOverallStatus::Active,
            networkStatus: ServiceNetworkStatus::Active
        );

        $invoice = $this->createInvoice($service, totalAmount: 145000, dueDate: '2026-04-30');

        $response = $this->postJson('/api/v1/admin/invoices/bulk-action', [
            'invoice_ids' => [$invoice->id],
            'action' => 'mark_paid',
            'payment_method' => 'bank_transfer',
            'paid_at' => '2026-04-21 15:00:00',
            'reference_no' => 'BULK-PAY-001',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.action', 'mark_paid')
            ->assertJsonPath('data.processed_count', 1)
            ->assertJsonPath('data.processed.0.invoice_id', $invoice->id);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'payment_status' => InvoicePaymentStatus::Paid->value,
        ]);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'payment_method' => 'bank_transfer',
            'reference_no' => 'BULK-PAY-001',
        ]);
        $this->assertDatabaseHas('cashflow_transactions', [
            'invoice_id' => $invoice->id,
            'direction' => 'income',
        ]);
    }

    public function test_it_marks_overdue_sends_whatsapp_and_triggers_auto_suspend(): void
    {
        Queue::fake();

        $service = $this->createService(
            serviceCode: 'SVC-AUTO-001',
            monthlyPrice: 120000,
            overallStatus: ServiceOverallStatus::Active,
            networkStatus: ServiceNetworkStatus::Active
        );

        $invoice = $this->createInvoice($service, totalAmount: 120000, dueDate: '2026-04-10');

        $this->patchJson('/api/v1/admin/invoices/'.$invoice->id.'/mark-overdue')
            ->assertOk()
            ->assertJsonPath('data.payment_status', InvoicePaymentStatus::Overdue->value);

        $this->postJson('/api/v1/admin/invoices/'.$invoice->id.'/send-whatsapp', [
            'phone' => '6281234567890',
        ])
            ->assertOk()
            ->assertJsonPath('data.dispatch.status', 'queued')
            ->assertJsonPath('data.dispatch.target_phone', '6281234567890');

        $this->postJson('/api/v1/admin/invoices/auto-suspend', [
            'service_id' => $service->id,
            'chunk' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.created_isolations', 1);

        $this->assertDatabaseHas('service_isolations', [
            'service_id' => $service->id,
            'invoice_id' => $invoice->id,
        ]);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 1);
    }

    private function createService(
        string $serviceCode,
        int $monthlyPrice,
        ServiceOverallStatus $overallStatus,
        ServiceNetworkStatus $networkStatus,
    ): Service {
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
            'monthly_price' => $monthlyPrice,
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
            'network_status' => $networkStatus->value,
            'overall_status' => $overallStatus->value,
            'activation_date' => '2026-04-01',
        ]);
    }

    private function createInvoice(
        Service $service,
        int $totalAmount,
        string $dueDate,
        InvoicePaymentStatus $paymentStatus = InvoicePaymentStatus::Unpaid,
        string $billingPeriod = '2026-04',
    ): Invoice {
        return Invoice::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'invoice_number' => 'INV-'.str_pad((string) $service->id, 6, '0', STR_PAD_LEFT).'-'.str_replace('-', '', $dueDate),
            'billing_period' => $billingPeriod,
            'invoice_date' => '2026-04-01',
            'due_date' => $dueDate,
            'subtotal' => number_format($totalAmount, 2, '.', ''),
            'penalty_amount' => '0.00',
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'payment_status' => $paymentStatus->value,
            'issued_at' => in_array($paymentStatus, [
                InvoicePaymentStatus::Issued,
                InvoicePaymentStatus::Overdue,
                InvoicePaymentStatus::PartiallyPaid,
                InvoicePaymentStatus::Paid,
            ], true) ? '2026-04-01 00:00:00' : null,
            'paid_at' => $paymentStatus === InvoicePaymentStatus::Paid ? '2026-04-20 12:00:00' : null,
            'overdue_marked_at' => $paymentStatus === InvoicePaymentStatus::Overdue ? '2026-04-21 08:00:00' : null,
            'status_changed_at' => '2026-04-01 00:00:00',
        ]);
    }
}
