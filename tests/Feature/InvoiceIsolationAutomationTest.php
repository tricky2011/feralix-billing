<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationStatus;
use App\Enums\ServiceIsolationTargetType;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\VidType;
use App\Jobs\ProcessServiceRouterOperationJob;
use App\Jobs\SyncInvoiceServiceIsolationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceIsolation;
use App\Models\ServiceRouterOperationJob;
use App\Models\Vid;
use App\Services\Billing\InvoiceIsolationAutomationService;
use App\Services\Mikrotik\MikrotikAddressListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class InvoiceIsolationAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-24 09:00:00');
        $this->authenticateApi();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_automatically_creates_isolation_when_invoice_becomes_overdue(): void
    {
        Queue::fake([ProcessServiceRouterOperationJob::class]);

        $service = $this->createService('SVC-AUTO-OD-001');
        $invoice = $this->createInvoice($service, dueDate: '2026-04-10');

        $this->patchJson('/api/v1/admin/invoices/'.$invoice->id.'/mark-overdue')
            ->assertOk()
            ->assertJsonPath('data.payment_status', InvoicePaymentStatus::Overdue->value);

        // Process the sync job that was dispatched by the observer
        $this->processSyncJob($invoice->id);

        $this->assertDatabaseHas('service_isolations', [
            'service_id' => $service->id,
            'invoice_id' => $invoice->id,
            'status' => ServiceIsolationStatus::Pending->value,
        ]);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 1);
    }

    public function test_it_automatically_releases_pending_isolation_when_invoice_is_paid(): void
    {
        Queue::fake([ProcessServiceRouterOperationJob::class]);

        $service = $this->createService('SVC-AUTO-PD-001');
        $invoice = $this->createInvoice(
            $service,
            dueDate: '2026-04-10',
            paymentStatus: InvoicePaymentStatus::Overdue,
        );

        // Process initial sync job
        $this->processSyncJob($invoice->id);

        $this->assertDatabaseHas('service_isolations', [
            'service_id' => $service->id,
            'invoice_id' => $invoice->id,
            'status' => ServiceIsolationStatus::Pending->value,
        ]);

        $this->patchJson('/api/v1/admin/invoices/'.$invoice->id.'/mark-paid', [
            'payment_method' => 'cash',
            'paid_at' => '2026-04-24 10:00:00',
            'reference_no' => 'AUTO-REL-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.invoice.payment_status', InvoicePaymentStatus::Paid->value);

        // Process release sync job
        $this->processSyncJob($invoice->id);

        $this->assertDatabaseHas('service_isolations', [
            'service_id' => $service->id,
            'invoice_id' => $invoice->id,
            'status' => ServiceIsolationStatus::Released->value,
        ]);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 2);
    }

    public function test_it_keeps_isolation_when_other_overdue_invoices_still_exist(): void
    {
        Queue::fake([ProcessServiceRouterOperationJob::class]);

        $service = $this->createService('SVC-AUTO-MULTI-001');
        $firstInvoice = $this->createInvoice(
            $service,
            dueDate: '2026-04-05',
            paymentStatus: InvoicePaymentStatus::Overdue,
            billingPeriod: '2026-03',
        );
        $secondInvoice = $this->createInvoice(
            $service,
            dueDate: '2026-04-10',
            paymentStatus: InvoicePaymentStatus::Overdue,
            billingPeriod: '2026-04',
        );

        // Process initial sync job for first invoice
        $this->processSyncJob($firstInvoice->id);

        $this->patchJson('/api/v1/admin/invoices/'.$firstInvoice->id.'/mark-paid', [
            'payment_method' => 'bank_transfer',
            'paid_at' => '2026-04-24 11:00:00',
            'reference_no' => 'AUTO-KEEP-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.invoice.payment_status', InvoicePaymentStatus::Paid->value);

        // Process sync job after payment - should keep isolation due to second overdue invoice
        $this->processSyncJob($firstInvoice->id);

        $this->assertDatabaseCount('service_isolations', 1);
        $this->assertDatabaseHas('service_isolations', [
            'service_id' => $service->id,
            'status' => ServiceIsolationStatus::Pending->value,
            'invoice_id' => $firstInvoice->id,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $secondInvoice->id,
            'payment_status' => InvoicePaymentStatus::Overdue->value,
        ]);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 1);
    }

    public function test_partial_payment_does_not_release_isolation(): void
    {
        Queue::fake([ProcessServiceRouterOperationJob::class]);

        $service = $this->createService('SVC-AUTO-PARTIAL-001');
        $invoice = $this->createInvoice(
            $service,
            dueDate: '2026-04-10',
            paymentStatus: InvoicePaymentStatus::Overdue,
        );

        // Process initial sync job
        $this->processSyncJob($invoice->id);

        $response = $this->postJson('/api/v1/admin/payments', [
            'invoice_id' => $invoice->id,
            'amount_paid' => 50000,
            'payment_method' => 'cash',
            'paid_at' => '2026-04-24 12:00:00',
            'reference_no' => 'AUTO-PARTIAL-001',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.invoice.payment_status', InvoicePaymentStatus::Overdue->value);

        $this->assertDatabaseHas('service_isolations', [
            'service_id' => $service->id,
            'invoice_id' => $invoice->id,
            'status' => ServiceIsolationStatus::Pending->value,
        ]);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 1);
    }

    public function test_canceled_invoice_does_not_trigger_isolation(): void
    {
        Queue::fake([
            SyncInvoiceServiceIsolationJob::class,
            ProcessServiceRouterOperationJob::class,
        ]);

        $service = $this->createService('SVC-AUTO-CANCEL-001');
        $invoice = $this->createInvoice(
            $service,
            dueDate: '2026-04-10',
            paymentStatus: InvoicePaymentStatus::Canceled,
        );

        $this->assertDatabaseCount('service_isolations', 0);
        Queue::assertNotPushed(SyncInvoiceServiceIsolationJob::class);
        Queue::assertNotPushed(ProcessServiceRouterOperationJob::class);

        $summary = app(InvoiceIsolationAutomationService::class)->syncForInvoice($invoice->id, [
            'trigger' => 'manual_replay_canceled',
        ]);

        $this->assertSame('skipped_no_billing_isolation_to_release', $summary['action']);
        $this->assertDatabaseCount('service_isolations', 0);
        Queue::assertNotPushed(ProcessServiceRouterOperationJob::class);
    }

    public function test_it_is_idempotent_when_invoice_isolation_sync_is_replayed(): void
    {
        Queue::fake([ProcessServiceRouterOperationJob::class]);

        $service = $this->createService('SVC-AUTO-IDEMP-001');
        $invoice = $this->createInvoice(
            $service,
            dueDate: '2026-04-10',
            paymentStatus: InvoicePaymentStatus::Overdue,
        );

        $automationService = app(InvoiceIsolationAutomationService::class);

        // Call sync twice - should be idempotent
        $automationService->syncForInvoice($invoice->id, [
            'trigger' => 'manual_replay_overdue',
        ]);
        $automationService->syncForInvoice($invoice->id, [
            'trigger' => 'manual_replay_overdue_again',
        ]);

        $this->assertDatabaseCount('service_isolations', 1);
        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 1);

        $this->patchJson('/api/v1/admin/invoices/'.$invoice->id.'/mark-paid', [
            'payment_method' => 'cash',
            'paid_at' => '2026-04-24 13:00:00',
            'reference_no' => 'AUTO-IDEMP-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.invoice.payment_status', InvoicePaymentStatus::Paid->value);

        $automationService->syncForInvoice($invoice->id, [
            'trigger' => 'manual_replay_paid',
        ]);
        $automationService->syncForInvoice($invoice->id, [
            'trigger' => 'manual_replay_paid_again',
        ]);

        $this->assertDatabaseCount('service_isolations', 1);
        $this->assertDatabaseHas('service_isolations', [
            'service_id' => $service->id,
            'invoice_id' => $invoice->id,
            'status' => ServiceIsolationStatus::Released->value,
        ]);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class, 2);
    }

    public function test_isolation_target_subnet_is_normalized_vid_network_cidr(): void
    {
        Queue::fake([ProcessServiceRouterOperationJob::class]);

        $service = $this->createServiceWithCustomVidSubnet('SVC-NORM-001', '10.40.50.5/29');
        $invoice = $this->createInvoice($service, dueDate: '2026-04-10');

        $this->patchJson('/api/v1/admin/invoices/'.$invoice->id.'/mark-overdue')
            ->assertOk();

        // Process sync job
        $this->processSyncJob($invoice->id);

        $this->assertDatabaseHas('service_isolations', [
            'service_id' => $service->id,
            'target_subnet' => '10.40.50.0/29',
            'target_identifier' => '10.40.50.0/29',
        ]);

        $operationJob = ServiceRouterOperationJob::query()
            ->whereHas('serviceIsolation', fn ($q) => $q->where('service_id', $service->id))
            ->firstOrFail();

        $this->assertSame('10.40.50.0/29', $operationJob->target_address);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class);
    }

    public function test_overdue_invoice_with_monitoring_vid_is_skipped_safely(): void
    {
        Queue::fake([ProcessServiceRouterOperationJob::class]);

        $service = $this->createServiceWithMonitoringVid('SVC-MON-SKIP-001');
        $invoice = $this->createInvoice($service, dueDate: '2026-04-10');

        $this->patchJson('/api/v1/admin/invoices/'.$invoice->id.'/mark-overdue')
            ->assertOk()
            ->assertJsonPath('data.payment_status', InvoicePaymentStatus::Overdue->value);

        // Process sync job - should skip due to monitoring VID
        $this->processSyncJob($invoice->id);

        $this->assertDatabaseCount('service_isolations', 0);
        Queue::assertNotPushed(ProcessServiceRouterOperationJob::class);
    }

    public function test_release_operation_passes_legacy_comment_patterns_to_address_list_service(): void
    {
        Queue::fake([ProcessServiceRouterOperationJob::class]);

        $capturedPatterns = null;

        $this->mock(MikrotikAddressListService::class, function (MockInterface $mock) use (&$capturedPatterns): void {
            // Note: ensureAddressListed is NOT called because ProcessServiceRouterOperationJob is faked
            // The isolate operation job is queued via DB::afterCommit but never runs

            $mock->shouldReceive('ensureAddressRemoved')
                ->once()
                ->withArgs(function ($router, string $listName, string $address, array $patterns) use (&$capturedPatterns): bool {
                    $capturedPatterns = $patterns;

                    return true;
                })
                ->andReturn(['action' => 'removed', 'matched' => 1, 'router_item_id' => null]);
        });

        Queue::fake([ProcessServiceRouterOperationJob::class]);

        $service = $this->createService('SVC-CMT-001');
        $invoice = $this->createInvoice($service, dueDate: '2026-04-10', paymentStatus: InvoicePaymentStatus::Issued);

        // Process sync job to create isolation
        $this->processSyncJob($invoice->id);

        $isolation = ServiceIsolation::query()
            ->where('service_id', $service->id)
            ->firstOrFail();
        $isolationId = $isolation->id;

        // Update isolation to Applied so release will dispatch the job
        $isolation->update(['status' => \App\Enums\ServiceIsolationStatus::Applied->value]);

        // Paying triggers observer → release isolation → ensureAddressRemoved mocked
        $this->patchJson('/api/v1/admin/invoices/'.$invoice->id.'/mark-paid', [
            'payment_method' => 'cash',
            'paid_at' => '2026-04-24 10:00:00',
            'reference_no' => 'CMT-TEST-001',
        ])->assertOk();

        // Trigger release by calling automation service directly (no overdue invoices = release)
        app(InvoiceIsolationAutomationService::class)->syncForInvoice($invoice->id, [
            'trigger' => 'test_release',
        ]);

        // ProcessServiceRouterOperationJob was queued via DB::afterCommit but is faked
        // Call the execution service directly to verify the patterns are passed correctly
        $operationJob = \App\Models\ServiceRouterOperationJob::query()
            ->where('service_isolation_id', $isolation->id)
            ->where('operation_type', \App\Enums\ServiceRouterOperationType::Release->value)
            ->first();

        if ($operationJob) {
            app(\App\Services\Provisioning\ServiceIsolationRouterExecutionService::class)->execute($operationJob->id);
        }

        $this->assertNotNull($capturedPatterns, 'ensureAddressRemoved was not called during release');
        $this->assertContains('SVC-CMT-001', $capturedPatterns);          // broad match
        $this->assertContains('service:SVC-CMT-001', $capturedPatterns);  // old format prefix
        $this->assertContains('isolation:'.$isolationId, $capturedPatterns); // isolation ID
    }

    public function test_no_ppp_operation_dispatched_for_address_list_isolation(): void
    {
        Queue::fake([ProcessServiceRouterOperationJob::class]);

        $service = $this->createService('SVC-NOPPP-001');
        $invoice = $this->createInvoice($service, dueDate: '2026-04-10');

        $this->patchJson('/api/v1/admin/invoices/'.$invoice->id.'/mark-overdue')
            ->assertOk();

        // Process sync job
        $this->processSyncJob($invoice->id);

        $isolation = ServiceIsolation::query()
            ->where('service_id', $service->id)
            ->firstOrFail();

        // Isolation must use subnet target (VLAN network), never PPP
        $this->assertSame(ServiceIsolationTargetType::Subnet->value, $isolation->target_type->value);

        $operationJob = ServiceRouterOperationJob::query()
            ->where('service_isolation_id', $isolation->id)
            ->firstOrFail();

        $this->assertSame('isolate', $operationJob->operation_type->value);
        $this->assertNotNull($operationJob->address_list_name);
        $this->assertNotNull($operationJob->target_address);

        // target_address must be a CIDR (IP/prefix), not a PPP username
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+\.\d+\/\d+$/', $operationJob->target_address);

        Queue::assertPushed(ProcessServiceRouterOperationJob::class);
    }

    /**
     * Process the SyncInvoiceServiceIsolationJob that was dispatched by the observer.
     */
    private function processSyncJob(int $invoiceId): void
    {
        $job = new SyncInvoiceServiceIsolationJob($invoiceId, [
            'trigger' => 'payment_status_changed',
            'current_status' => InvoicePaymentStatus::Overdue->value,
        ]);

        Bus::dispatchSync($job);
    }

    private function createService(string $serviceCode): Service
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
            'monthly_price' => 200000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        $router = Router::query()->create([
            'router_code' => 'RTR-'.$serviceCode,
            'router_name' => 'Router '.$serviceCode,
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.40.'.random_int(10, 200),
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_active' => true,
        ]);

        $vid = Vid::query()->create([
            'router_id' => $router->id,
            'vid' => 320,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => '10.40.50.0/29',
            'gateway_ip' => '10.40.50.1',
            'pool_start_ip' => '10.40.50.2',
            'pool_end_ip' => '10.40.50.6',
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
            'internet_vid' => 320,
            'subnet_cidr' => '10.40.50.0/29',
            'gateway_ip' => '10.40.50.1',
            'dhcp_pool_start' => '10.40.50.2',
            'dhcp_pool_end' => '10.40.50.6',
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
        string $dueDate,
        InvoicePaymentStatus $paymentStatus = InvoicePaymentStatus::Issued,
        string $billingPeriod = '2026-04',
    ): Invoice {
        return Invoice::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'invoice_number' => 'INV-'.str_pad((string) $service->id, 6, '0', STR_PAD_LEFT).'-'.str_replace('-', '', $dueDate),
            'billing_period' => $billingPeriod,
            'invoice_date' => '2026-04-01',
            'due_date' => $dueDate,
            'subtotal' => '200000.00',
            'penalty_amount' => '0.00',
            'total_amount' => '200000.00',
            'payment_status' => $paymentStatus->value,
            'issued_at' => in_array($paymentStatus, [
                InvoicePaymentStatus::Issued,
                InvoicePaymentStatus::Overdue,
                InvoicePaymentStatus::PartiallyPaid,
                InvoicePaymentStatus::Paid,
                InvoicePaymentStatus::Canceled,
            ], true) ? '2026-04-01 00:00:00' : null,
            'paid_at' => $paymentStatus === InvoicePaymentStatus::Paid ? '2026-04-24 08:00:00' : null,
            'overdue_marked_at' => $paymentStatus === InvoicePaymentStatus::Overdue ? '2026-04-24 09:00:00' : null,
            'status_changed_at' => '2026-04-01 00:00:00',
        ]);
    }

    private function createServiceWithCustomVidSubnet(string $serviceCode, string $vidSubnetCidr): Service
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
            'monthly_price' => 200000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        $router = Router::query()->create([
            'router_code' => 'RTR-'.$serviceCode,
            'router_name' => 'Router '.$serviceCode,
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.41.'.random_int(10, 200),
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_active' => true,
        ]);

        $vid = Vid::query()->create([
            'router_id' => $router->id,
            'vid' => 321,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => $vidSubnetCidr,
            'gateway_ip' => '10.40.50.1',
            'pool_start_ip' => '10.40.50.2',
            'pool_end_ip' => '10.40.50.6',
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
            'internet_vid' => 321,
            'subnet_cidr' => $vidSubnetCidr,
            'gateway_ip' => '10.40.50.1',
            'dhcp_pool_start' => '10.40.50.2',
            'dhcp_pool_end' => '10.40.50.6',
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

    private function createServiceWithMonitoringVid(string $serviceCode): Service
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
            'monthly_price' => 200000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        $router = Router::query()->create([
            'router_code' => 'RTR-'.$serviceCode,
            'router_name' => 'Router '.$serviceCode,
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.42.'.random_int(10, 200),
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_active' => true,
        ]);

        $vid = Vid::query()->create([
            'router_id' => $router->id,
            'vid' => 100,
            'vid_type' => VidType::Monitoring->value,
            'subnet_cidr' => '10.40.60.0/29',
            'gateway_ip' => '10.40.60.1',
            'pool_start_ip' => '10.40.60.2',
            'pool_end_ip' => '10.40.60.6',
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
            'internet_vid' => 100,
            'subnet_cidr' => '10.40.60.0/29',
            'gateway_ip' => '10.40.60.1',
            'dhcp_pool_start' => '10.40.60.2',
            'dhcp_pool_end' => '10.40.60.6',
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
