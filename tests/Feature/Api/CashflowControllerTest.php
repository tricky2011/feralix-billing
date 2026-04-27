<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Models\Cashflow;
use App\Models\CashflowCategory;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CashflowControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipApiAuthentication = true;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_create_income_manual(): void
    {
        $admin = $this->authenticateApi(User::factory()->admin()->create());
        $category = CashflowCategory::query()->create([
            'name' => 'Sales',
            'type' => 'income',
            'description' => 'Income category',
        ]);

        $response = $this->postJson('/api/v1/admin/cashflows', [
            'type' => 'income',
            'amount' => 250000,
            'category_id' => $category->id,
            'description' => 'Pembayaran instalasi',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'income')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.amount', '250000.00')
            ->assertJsonPath('data.category.id', $category->id);

        $this->assertDatabaseHas('cashflows', [
            'type' => 'income',
            'amount' => '250000.00',
            'category_id' => $category->id,
            'source' => 'manual',
            'created_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'cashflow.created',
            'module' => 'cashflow',
            'user_id' => $admin->id,
        ]);
    }

    public function test_create_expense_manual(): void
    {
        $this->authenticateApi(User::factory()->admin()->create());

        $response = $this->postJson('/api/v1/admin/cashflows', [
            'type' => 'expense',
            'amount' => 125000,
            'description' => 'Pembelian kabel',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.amount', '125000.00');

        $this->assertDatabaseHas('cashflows', [
            'type' => 'expense',
            'amount' => '125000.00',
            'source' => 'manual',
            'description' => 'Pembelian kabel',
        ]);
    }

    public function test_update_manual_cashflow(): void
    {
        $admin = $this->authenticateApi(User::factory()->admin()->create());
        $expenseCategory = CashflowCategory::query()->create([
            'name' => 'Operational',
            'type' => 'expense',
            'description' => 'Expense category',
        ]);

        $cashflow = Cashflow::query()->create([
            'type' => 'income',
            'amount' => '90000.00',
            'description' => 'Initial record',
            'source' => 'manual',
            'created_by' => $admin->id,
        ]);

        $response = $this->patchJson("/api/v1/admin/cashflows/{$cashflow->id}", [
            'type' => 'expense',
            'amount' => 75000,
            'category_id' => $expenseCategory->id,
            'description' => 'Update operasional',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $cashflow->id)
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.amount', '75000.00')
            ->assertJsonPath('data.category.id', $expenseCategory->id);

        $this->assertDatabaseHas('cashflows', [
            'id' => $cashflow->id,
            'type' => 'expense',
            'amount' => '75000.00',
            'category_id' => $expenseCategory->id,
            'description' => 'Update operasional',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'cashflow.updated',
            'module' => 'cashflow',
            'user_id' => $admin->id,
        ]);
    }

    public function test_delete_manual_cashflow(): void
    {
        $admin = $this->authenticateApi(User::factory()->admin()->create());

        $cashflow = Cashflow::query()->create([
            'type' => 'expense',
            'amount' => '32000.00',
            'description' => 'Delete target',
            'source' => 'manual',
            'created_by' => $admin->id,
        ]);

        $this->deleteJson("/api/v1/admin/cashflows/{$cashflow->id}")
            ->assertOk();

        $this->assertDatabaseMissing('cashflows', [
            'id' => $cashflow->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'cashflow.deleted',
            'module' => 'cashflow',
            'user_id' => $admin->id,
        ]);
    }

    public function test_system_cashflow_cannot_be_updated_or_deleted(): void
    {
        $this->authenticateApi(User::factory()->admin()->create());

        $service = $this->createService('CFSYS001');
        $invoice = $this->createInvoice($service, 'INV-CFSYS-001', '2026-04', 150000);

        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'amount_paid' => '150000.00',
            'payment_method' => 'cash',
            'paid_at' => '2026-04-21 09:30:00',
        ]);

        $cashflow = Cashflow::query()
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($cashflow);
        $this->assertSame('system', $cashflow->source);

        $this->patchJson("/api/v1/admin/cashflows/{$cashflow->id}", [
            'type' => 'income',
            'amount' => 1,
            'description' => 'Should fail',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source']);

        $this->deleteJson("/api/v1/admin/cashflows/{$cashflow->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source']);

        $this->assertDatabaseHas('cashflows', [
            'id' => $cashflow->id,
            'source' => 'system',
        ]);
    }

    public function test_filter_works(): void
    {
        $this->authenticateApi(User::factory()->admin()->create());

        $incomeCategory = CashflowCategory::query()->create([
            'name' => 'Sales',
            'type' => 'income',
            'description' => null,
        ]);
        $expenseCategory = CashflowCategory::query()->create([
            'name' => 'Operations',
            'type' => 'expense',
            'description' => null,
        ]);

        $this->createManualCashflow('expense', 50000, $expenseCategory->id, 'Bensin operasional', '2026-04-15 08:00:00');
        $this->createManualCashflow('expense', 30000, $expenseCategory->id, 'Sewa kantor', '2026-03-15 08:00:00');
        $this->createManualCashflow('income', 200000, $incomeCategory->id, 'Pendapatan reseller', '2026-04-18 08:00:00');

        $response = $this->getJson('/api/v1/admin/cashflows?type=expense&category_id='.$expenseCategory->id.'&date_from=2026-04-01&date_to=2026-04-30&search=bensin');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'expense')
            ->assertJsonPath('data.0.description', 'Bensin operasional')
            ->assertJsonPath('meta.filters.type', 'expense')
            ->assertJsonPath('meta.filters.category_id', (string) $expenseCategory->id);
    }

    public function test_summary_is_correct(): void
    {
        $this->authenticateApi(User::factory()->admin()->create());

        $this->createManualCashflow('income', 100000, null, 'Income A', '2026-04-10 09:00:00');
        $this->createManualCashflow('income', 50000, null, 'Income B', '2026-04-11 09:00:00');
        $this->createManualCashflow('expense', 30000, null, 'Expense A', '2026-04-12 09:00:00');
        $this->createManualCashflow('expense', 5000, null, 'Expense old', '2026-03-01 09:00:00');

        $this->getJson('/api/v1/admin/cashflows/summary?date_from=2026-04-01&date_to=2026-04-30')
            ->assertOk()
            ->assertJsonPath('data.total_income', '150000.00')
            ->assertJsonPath('data.total_expense', '30000.00')
            ->assertJsonPath('data.net_balance', '120000.00');
    }

    public function test_unauthorized_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/admin/cashflows')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('meta.code', 'api_authentication_required');

        $this->postJson('/api/v1/admin/cashflows', [
            'type' => 'income',
            'amount' => 1000,
        ])
            ->assertUnauthorized();
    }

    public function test_technician_is_forbidden(): void
    {
        $this->authenticateApi(User::factory()->technician()->create());

        $this->getJson('/api/v1/admin/cashflows')
            ->assertForbidden();
    }

    private function createManualCashflow(
        string $type,
        float $amount,
        ?int $categoryId,
        string $description,
        string $createdAt,
    ): Cashflow {
        $cashflow = Cashflow::query()->create([
            'type' => $type,
            'amount' => number_format($amount, 2, '.', ''),
            'category_id' => $categoryId,
            'description' => $description,
            'source' => 'manual',
            'created_by' => null,
        ]);

        $cashflow->forceFill([
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ])->save();

        return $cashflow->refresh();
    }

    private function createService(string $suffix): Service
    {
        $customer = Customer::query()->create([
            'customer_code' => 'CUST-'.$suffix,
            'full_name' => 'Customer '.$suffix,
            'phone' => '081234567890',
            'address' => 'Jl. Cashflow '.$suffix,
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);

        $package = Package::query()->create([
            'package_name' => 'Package '.$suffix,
            'monthly_price' => 150000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        $router = Router::query()->create([
            'router_code' => 'RTR-'.$suffix,
            'router_name' => 'Router '.$suffix,
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
            'service_code' => 'SVC-'.$suffix,
            'monitor_vid' => 100,
            'monitor_pppoe_username' => 'monitor-'.$suffix,
            'monitor_pppoe_password' => 'secret-monitor',
            'internet_vid' => 300,
            'subnet_cidr' => '10.0.0.0/24',
            'gateway_ip' => '10.0.0.1',
            'dhcp_pool_start' => '10.0.0.10',
            'dhcp_pool_end' => '10.0.0.50',
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'FTTH-'.$suffix,
            'billing_status' => ServiceBillingStatus::Pending->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-01',
        ]);
    }

    private function createInvoice(Service $service, string $invoiceNumber, string $billingPeriod, int $amount): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'invoice_number' => $invoiceNumber,
            'billing_period' => $billingPeriod,
            'invoice_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => number_format($amount, 2, '.', ''),
            'penalty_amount' => '0.00',
            'total_amount' => number_format($amount, 2, '.', ''),
        ]);
    }
}
