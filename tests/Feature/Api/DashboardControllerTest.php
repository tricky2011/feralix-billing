<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\MonitorPppoeStatus;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\TicketAssignmentMode;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\BusinessExpense;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceMonitorPppoeLog;
use App\Models\ServiceMonitorPppoeStatus;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-22 09:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_superadmin_can_view_dashboard_and_switch_active_router(): void
    {
        $technician = $this->createTechnician('TECH-DSH-01', 'Teknisi Dashboard');
        $routerA = $this->createRouter('A');
        $routerB = $this->createRouter('B');
        $serviceA = $this->createService('A', $routerA, 150000, ServiceOverallStatus::Active);
        $serviceB = $this->createService('B', $routerB, 175000, ServiceOverallStatus::Suspended);

        $invoiceA = $this->createInvoice($serviceA, 'INV-A', '2026-04', 150000, InvoicePaymentStatus::Paid);
        $invoiceB = $this->createInvoice($serviceB, 'INV-B', '2026-04', 175000, InvoicePaymentStatus::Unpaid);
        $this->createPayment($invoiceA, $serviceA, 150000, '2026-04-05 10:00:00');

        BusinessExpense::query()->create([
            'router_id' => $routerA->id,
            'expense_date' => '2026-04-03',
            'category' => 'maintenance',
            'description' => 'Maintenance A',
            'amount' => '20000.00',
        ]);
        BusinessExpense::query()->create([
            'router_id' => $routerB->id,
            'expense_date' => '2026-04-04',
            'category' => 'maintenance',
            'description' => 'Maintenance B',
            'amount' => '30000.00',
        ]);

        $this->createWorkOrder(
            'WO-A',
            $serviceA,
            $routerA,
            $technician,
            WorkOrderType::NewInstall,
            WorkOrderStatus::Completed,
            '2026-04-10 08:00:00',
            '2026-04-10 12:00:00',
        );
        $this->createTicket(
            'TCK-A',
            $serviceA,
            $technician,
            TicketStatus::Resolved,
            '2026-04-11 09:00:00',
        );

        $this->createPppStatus($serviceA, $routerA, MonitorPppoeStatus::Online, '2026-04-22 08:50:00');
        $this->createPppStatus($serviceB, $routerB, MonitorPppoeStatus::Offline, '2026-04-22 08:55:00');
        $this->createPppLog($serviceA, $routerA, null, MonitorPppoeStatus::Online, '2026-04-18 09:00:00');
        $this->createPppLog($serviceB, $routerB, MonitorPppoeStatus::Online, MonitorPppoeStatus::Offline, '2026-04-19 09:00:00');

        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('data.dashboard_type', 'admin')
            ->assertJsonPath('data.scope.role', UserRole::Superadmin->value)
            ->assertJsonPath('data.scope.router_switcher.enabled', true)
            ->assertJsonPath('data.scope.router_switcher.is_all_routers', true)
            ->assertJsonPath('data.kpis.total_customers.value', 2)
            ->assertJsonPath('data.kpis.customer_active.value', 1)
            ->assertJsonPath('data.kpis.customer_suspended.value', 1)
            ->assertJsonPath('data.kpis.invoice_unpaid.value', 1)
            ->assertJsonPath('data.kpis.monthly_income.value', '150000.00')
            ->assertJsonPath('data.kpis.monthly_expense.value', '50000.00')
            ->assertJsonPath('data.kpis.monthly_profit.value', '100000.00')
            ->assertJsonPath('data.summaries.installations.completed', 1)
            ->assertJsonPath('data.summaries.tickets.total', 1)
            ->assertJsonPath('data.summaries.ppp.active', 1);

        $this->assertCount(12, $response->json('data.charts.monthly_revenue.points'));
        $this->assertCount(14, $response->json('data.charts.ppp_active_trend.points'));

        $switchResponse = $this->actingAs($user)->patchJson('/api/v1/admin/dashboard/router-switch', [
            'router_id' => $routerA->id,
        ]);

        $switchResponse
            ->assertOk()
            ->assertJsonPath('data.scope.router_switcher.active_router_id', $routerA->id)
            ->assertJsonPath('data.scope.router_switcher.is_all_routers', false)
            ->assertJsonPath('data.kpis.total_customers.value', 1)
            ->assertJsonPath('data.kpis.monthly_income.value', '150000.00')
            ->assertJsonPath('data.kpis.monthly_expense.value', '20000.00')
            ->assertJsonPath('data.kpis.monthly_profit.value', '130000.00')
            ->assertJsonPath('data.summaries.ppp.active', 1);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'dashboard_active_router_id' => $routerA->id,
        ]);
    }

    public function test_admin_only_sees_data_within_assigned_router_scope(): void
    {
        $routerA = $this->createRouter('C');
        $routerB = $this->createRouter('D');
        $serviceA = $this->createService('C', $routerA, 110000, ServiceOverallStatus::Active);
        $serviceB = $this->createService('D', $routerB, 210000, ServiceOverallStatus::Active);

        $invoiceA = $this->createInvoice($serviceA, 'INV-C', '2026-04', 110000, InvoicePaymentStatus::Paid);
        $invoiceB = $this->createInvoice($serviceB, 'INV-D', '2026-04', 210000, InvoicePaymentStatus::Paid);
        $this->createPayment($invoiceA, $serviceA, 110000, '2026-04-06 10:00:00');
        $this->createPayment($invoiceB, $serviceB, 210000, '2026-04-07 10:00:00');

        BusinessExpense::query()->create([
            'router_id' => $routerA->id,
            'expense_date' => '2026-04-06',
            'category' => 'power',
            'description' => 'Scope A',
            'amount' => '15000.00',
        ]);
        BusinessExpense::query()->create([
            'router_id' => $routerB->id,
            'expense_date' => '2026-04-07',
            'category' => 'power',
            'description' => 'Scope B',
            'amount' => '5000.00',
        ]);

        $admin = User::factory()->admin()->create();
        $admin->accessibleRouters()->attach($routerA->id);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('data.scope.role', UserRole::Admin->value)
            ->assertJsonPath('data.scope.router_switcher.enabled', false)
            ->assertJsonPath('data.scope.router_switcher.visible_router_ids.0', $routerA->id)
            ->assertJsonPath('data.kpis.total_customers.value', 1)
            ->assertJsonPath('data.kpis.monthly_income.value', '110000.00')
            ->assertJsonPath('data.kpis.monthly_expense.value', '15000.00')
            ->assertJsonPath('data.kpis.monthly_profit.value', '95000.00');
    }

    public function test_technician_user_is_redirected_to_technician_dashboard(): void
    {
        $technician = $this->createTechnician('TECH-DSH-02', 'Teknisi Lapangan');
        $router = $this->createRouter('E');
        $service = $this->createService('E', $router, 120000, ServiceOverallStatus::Active);

        $this->createTicket(
            'TCK-E',
            $service,
            $technician,
            TicketStatus::Open,
            '2026-04-12 09:00:00',
        );
        $this->createWorkOrder(
            'WO-E',
            $service,
            $router,
            $technician,
            WorkOrderType::NewInstall,
            WorkOrderStatus::Completed,
            '2026-04-12 08:00:00',
            '2026-04-12 12:00:00',
        );

        $user = User::factory()->technician($technician->id)->create();

        $adminDashboardResponse = $this->actingAs($user)->getJson('/api/v1/admin/dashboard');

        $adminDashboardResponse
            ->assertStatus(409)
            ->assertJsonPath('data.target_dashboard', 'technician')
            ->assertJsonPath('data.redirect_to', '/api/v1/technician/dashboard');

        $technicianDashboardResponse = $this->actingAs($user)->getJson('/api/v1/technician/dashboard');

        $technicianDashboardResponse
            ->assertOk()
            ->assertJsonPath('data.dashboard_type', 'technician')
            ->assertJsonPath('data.technician.id', $technician->id)
            ->assertJsonPath('data.kpis.open_tickets.value', 1)
            ->assertJsonPath('data.kpis.month_completed_work_orders.value', 1)
            ->assertJsonPath('data.kpis.month_completed_installations.value', 1);
    }

    private function createRouter(string $suffix): Router
    {
        return Router::query()->create([
            'router_code' => 'RTR-'.$suffix,
            'router_name' => 'Router '.$suffix,
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.'.$this->sequenceDigit($suffix).'.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'location_name' => 'Site '.$suffix,
            'is_active' => true,
        ]);
    }

    private function createService(
        string $suffix,
        Router $router,
        int $monthlyPrice,
        ServiceOverallStatus $overallStatus,
    ): Service {
        $customer = Customer::query()->create([
            'customer_code' => 'CUST-'.$suffix,
            'full_name' => 'Customer '.$suffix,
            'phone' => '08123'.$this->sequenceDigit($suffix).'56789',
            'address' => 'Alamat '.$suffix,
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);

        $package = Package::query()->create([
            'package_name' => 'Package '.$suffix,
            'monthly_price' => $monthlyPrice,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        return Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'service_code' => 'SVC-'.$suffix,
            'monitor_vid' => 100 + $this->sequenceDigit($suffix),
            'monitor_pppoe_username' => 'pppoe.'.$suffix,
            'monitor_pppoe_password' => 'secret-monitor',
            'internet_vid' => 200 + $this->sequenceDigit($suffix),
            'subnet_cidr' => '10.0.'.$this->sequenceDigit($suffix).'.0/24',
            'gateway_ip' => '10.0.'.$this->sequenceDigit($suffix).'.1',
            'dhcp_pool_start' => '10.0.'.$this->sequenceDigit($suffix).'.10',
            'dhcp_pool_end' => '10.0.'.$this->sequenceDigit($suffix).'.50',
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'ADDR-'.$suffix,
            'billing_status' => ServiceBillingStatus::Pending->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => $overallStatus->value,
            'activation_date' => '2026-04-01',
        ]);
    }

    private function createInvoice(
        Service $service,
        string $suffix,
        string $billingPeriod,
        int $totalAmount,
        InvoicePaymentStatus $paymentStatus,
    ): Invoice {
        return Invoice::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'invoice_number' => $suffix,
            'billing_period' => $billingPeriod,
            'invoice_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => number_format($totalAmount, 2, '.', ''),
            'penalty_amount' => '0.00',
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'payment_status' => $paymentStatus->value,
        ]);
    }

    private function createPayment(Invoice $invoice, Service $service, int $amount, string $paidAt): Payment
    {
        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'amount_paid' => number_format($amount, 2, '.', ''),
            'payment_method' => 'cash',
            'paid_at' => $paidAt,
            'reference_no' => 'PAY-'.$service->service_code,
        ]);
    }

    private function createTechnician(string $technicianCode, string $fullName): Technician
    {
        return Technician::query()->create([
            'technician_code' => $technicianCode,
            'full_name' => $fullName,
            'phone' => '081200000000',
            'is_active' => true,
        ]);
    }

    private function createWorkOrder(
        string $suffix,
        Service $service,
        Router $router,
        Technician $technician,
        WorkOrderType $type,
        WorkOrderStatus $status,
        string $createdAt,
        ?string $completedAt = null,
    ): WorkOrder {
        $workOrder = WorkOrder::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'router_id' => $router->id,
            'wo_number' => $suffix,
            'wo_type' => $type->value,
            'assigned_technician_id' => $technician->id,
            'planned_monitor_vid' => $service->monitor_vid,
            'planned_internet_vid' => $service->internet_vid,
            'planned_subnet_cidr' => $service->subnet_cidr,
            'status' => $status->value,
            'work_notes' => 'Work order '.$suffix,
            'completed_at' => $completedAt,
        ]);

        $workOrder->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $completedAt ?? $createdAt,
        ])->saveQuietly();

        return $workOrder->refresh();
    }

    private function createTicket(
        string $suffix,
        Service $service,
        Technician $technician,
        TicketStatus $status,
        string $createdAt,
    ): Ticket {
        $ticket = Ticket::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'ticket_number' => $suffix,
            'category' => 'network_issue',
            'priority' => TicketPriority::Medium->value,
            'description' => 'Ticket '.$suffix,
            'assigned_technician_id' => $technician->id,
            'assignment_mode' => TicketAssignmentMode::Manual->value,
            'status' => $status->value,
        ]);

        $ticket->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $ticket->refresh();
    }

    private function createPppStatus(
        Service $service,
        Router $router,
        MonitorPppoeStatus $status,
        string $lastSyncedAt,
    ): ServiceMonitorPppoeStatus {
        return ServiceMonitorPppoeStatus::query()->create([
            'service_id' => $service->id,
            'router_id' => $router->id,
            'monitor_pppoe_username' => $service->monitor_pppoe_username,
            'status' => $status->value,
            'base_overall_status' => $service->overall_status->value,
            'last_seen_at' => $status === MonitorPppoeStatus::Online ? $lastSyncedAt : null,
            'last_synced_at' => $lastSyncedAt,
            'last_status_changed_at' => $lastSyncedAt,
        ]);
    }

    private function createPppLog(
        Service $service,
        Router $router,
        ?MonitorPppoeStatus $previousStatus,
        MonitorPppoeStatus $status,
        string $observedAt,
    ): ServiceMonitorPppoeLog {
        return ServiceMonitorPppoeLog::query()->create([
            'service_id' => $service->id,
            'router_id' => $router->id,
            'monitor_pppoe_username' => $service->monitor_pppoe_username,
            'previous_status' => $previousStatus?->value,
            'status' => $status->value,
            'last_seen_at' => $status === MonitorPppoeStatus::Online ? $observedAt : null,
            'observed_at' => $observedAt,
        ]);
    }

    private function sequenceDigit(string $suffix): int
    {
        return max(1, ord($suffix[0]) - 64);
    }
}
