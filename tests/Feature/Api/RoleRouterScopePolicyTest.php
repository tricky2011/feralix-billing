<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\TicketAssignmentMode;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\VidStatus;
use App\Enums\VidType;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Router;
use App\Models\RouterScope;
use App\Models\Service;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vid;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleRouterScopePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipApiAuthentication = true;

    public function test_authenticated_technician_is_blocked_from_admin_operational_routes(): void
    {
        $technician = $this->createTechnician('TECH-POL-01', 'Teknisi Policy');
        $user = User::factory()->technician($technician->id)->create();

        $this->authenticateApi($user);

        $this->getJson('/api/v1/admin/customers')
            ->assertForbidden();
    }

    public function test_admin_indexes_are_limited_to_assigned_router_scope(): void
    {
        $routerA = $this->createRouter('A');
        $routerB = $this->createRouter('B');
        $technician = $this->createTechnician('TECH-POL-02', 'Teknisi Scope');

        $serviceA = $this->createService('A', $routerA, 150000, ServiceOverallStatus::Active);
        $serviceB = $this->createService('B', $routerB, 175000, ServiceOverallStatus::Active);

        $invoiceA = $this->createInvoice($serviceA, 'INV-SCOPE-A', '2026-04', 150000, InvoicePaymentStatus::Paid);
        $invoiceB = $this->createInvoice($serviceB, 'INV-SCOPE-B', '2026-04', 175000, InvoicePaymentStatus::Paid);
        $paymentA = $this->createPayment($invoiceA, $serviceA, 150000);
        $this->createPayment($invoiceB, $serviceB, 175000);
        $ticketA = $this->createTicket('TCK-SCOPE-A', $serviceA, $technician);
        $this->createTicket('TCK-SCOPE-B', $serviceB, $technician);
        $workOrderA = $this->createWorkOrder('WO-SCOPE-A', $serviceA, $routerA, $technician);
        $this->createWorkOrder('WO-SCOPE-B', $serviceB, $routerB, $technician);

        $admin = User::factory()->admin()->create();
        $admin->accessibleRouters()->attach($routerA->id);

        $this->authenticateApi($admin);

        $this->getJson('/api/v1/admin/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $serviceA->customer_id);

        $this->authenticateApi($admin);

        $this->getJson('/api/v1/admin/services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $serviceA->id);

        $this->authenticateApi($admin);

        $this->getJson('/api/v1/admin/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $invoiceA->id);

        $this->authenticateApi($admin);

        $this->getJson('/api/v1/admin/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $paymentA->id);

        $this->authenticateApi($admin);

        $this->getJson('/api/v1/admin/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ticketA->id);

        $this->authenticateApi($admin);

        $this->getJson('/api/v1/admin/work-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $workOrderA->id);
    }

    public function test_admin_cannot_access_router_scoped_bindings_outside_assignment(): void
    {
        $routerA = $this->createRouter('C');
        $routerB = $this->createRouter('D');
        $serviceB = $this->createService('C', $routerB, 125000, ServiceOverallStatus::Active);
        $invoiceB = $this->createInvoice($serviceB, 'INV-BIND-B', '2026-04', 125000, InvoicePaymentStatus::Unpaid);
        $scopeB = $this->createRouterScope($routerB, 'Scope Binding B');

        $admin = User::factory()->admin()->create();
        $admin->accessibleRouters()->attach($routerA->id);

        $this->authenticateApi($admin);

        $this->getJson("/api/v1/admin/services/{$serviceB->id}")
            ->assertForbidden();

        $this->authenticateApi($admin);

        $this->getJson("/api/v1/admin/invoices/{$invoiceB->id}")
            ->assertForbidden();

        $this->authenticateApi($admin);

        $this->getJson("/api/v1/admin/routers/{$routerB->id}")
            ->assertForbidden();

        $this->authenticateApi($admin);

        $this->getJson("/api/v1/admin/router-scopes/{$scopeB->id}")
            ->assertForbidden();

        $this->authenticateApi($admin);

        $this->getJson('/api/v1/technician/dashboard')
            ->assertForbidden();
    }

    public function test_admin_reference_and_vid_endpoints_only_expose_assigned_router_inventory(): void
    {
        $routerA = $this->createRouter('E');
        $routerB = $this->createRouter('F');
        $scopeA = $this->createRouterScope($routerA, 'Scope A');
        $scopeB = $this->createRouterScope($routerB, 'Scope B');
        $vidA = $this->createVid($routerA, $scopeA, 310);
        $this->createVid($routerB, $scopeB, 410);

        $admin = User::factory()->admin()->create();
        $admin->accessibleRouters()->attach($routerA->id);

        $this->authenticateApi($admin);

        $this->getJson('/api/v1/admin/customer-references')
            ->assertOk()
            ->assertJsonCount(1, 'data.routers')
            ->assertJsonPath('data.routers.0.id', $routerA->id)
            ->assertJsonCount(1, 'data.available_vids')
            ->assertJsonPath('data.available_vids.0.id', $vidA->id);

        $this->authenticateApi($admin);

        $this->getJson('/api/v1/admin/vids')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $vidA->id);
    }

    private function createRouter(string $suffix): Router
    {
        $digit = $this->sequenceDigit($suffix);

        return Router::query()->create([
            'router_code' => 'RTR-POL-'.$suffix,
            'router_name' => 'Router Policy '.$suffix,
            'router_role' => 'bng',
            'mgmt_ip' => '10.30.'.$digit.'.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'location_name' => 'POP '.$suffix,
            'is_active' => true,
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

    private function createService(
        string $suffix,
        Router $router,
        int $monthlyPrice,
        ServiceOverallStatus $overallStatus,
    ): Service {
        $digit = $this->sequenceDigit($suffix);
        $customer = Customer::query()->create([
            'customer_code' => 'CUST-POL-'.$suffix,
            'full_name' => 'Customer Policy '.$suffix,
            'phone' => '08123'.$digit.'56789',
            'address' => 'Alamat Policy '.$suffix,
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);

        $package = Package::query()->create([
            'package_name' => 'Package Policy '.$suffix,
            'monthly_price' => $monthlyPrice,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        return Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'service_code' => 'SVC-POL-'.$suffix,
            'monitor_vid' => 100 + $digit,
            'monitor_pppoe_username' => 'pppoe.policy.'.$suffix,
            'monitor_pppoe_password' => 'secret-monitor',
            'internet_vid' => 200 + $digit,
            'subnet_cidr' => '10.10.'.$digit.'.0/24',
            'gateway_ip' => '10.10.'.$digit.'.1',
            'dhcp_pool_start' => '10.10.'.$digit.'.10',
            'dhcp_pool_end' => '10.10.'.$digit.'.50',
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'ADDR-POL-'.$suffix,
            'billing_status' => ServiceBillingStatus::Pending->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => $overallStatus->value,
            'activation_date' => '2026-04-01',
        ]);
    }

    private function createInvoice(
        Service $service,
        string $invoiceNumber,
        string $billingPeriod,
        int $totalAmount,
        InvoicePaymentStatus $status,
    ): Invoice {
        return Invoice::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'invoice_number' => $invoiceNumber,
            'billing_period' => $billingPeriod,
            'invoice_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => number_format($totalAmount, 2, '.', ''),
            'penalty_amount' => '0.00',
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'payment_status' => $status->value,
        ]);
    }

    private function createPayment(Invoice $invoice, Service $service, int $amount): Payment
    {
        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'amount_paid' => number_format($amount, 2, '.', ''),
            'payment_method' => 'cash',
            'paid_at' => '2026-04-05 10:00:00',
            'reference_no' => 'PAY-'.$service->service_code,
        ]);
    }

    private function createTicket(string $ticketNumber, Service $service, Technician $technician): Ticket
    {
        return Ticket::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'ticket_number' => $ticketNumber,
            'category' => 'network_issue',
            'priority' => TicketPriority::Medium->value,
            'description' => 'Ticket '.$ticketNumber,
            'assigned_technician_id' => $technician->id,
            'assignment_mode' => TicketAssignmentMode::Manual->value,
            'status' => TicketStatus::Assigned->value,
        ]);
    }

    private function createWorkOrder(
        string $workOrderNumber,
        Service $service,
        Router $router,
        Technician $technician,
    ): WorkOrder {
        return WorkOrder::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'router_id' => $router->id,
            'wo_number' => $workOrderNumber,
            'wo_type' => WorkOrderType::NewInstall->value,
            'assigned_technician_id' => $technician->id,
            'planned_monitor_vid' => $service->monitor_vid,
            'planned_internet_vid' => $service->internet_vid,
            'planned_subnet_cidr' => $service->subnet_cidr,
            'status' => WorkOrderStatus::Assigned->value,
            'work_notes' => 'Work order '.$workOrderNumber,
        ]);
    }

    private function createRouterScope(Router $router, string $scopeName): RouterScope
    {
        return RouterScope::query()->create([
            'router_id' => $router->id,
            'scope_name' => $scopeName,
            'monitor_vid' => 101,
            'vid_start' => 300,
            'vid_end' => 399,
            'is_special' => false,
        ]);
    }

    private function createVid(Router $router, RouterScope $scope, int $vidNumber): Vid
    {
        return Vid::query()->create([
            'router_id' => $router->id,
            'scope_id' => $scope->id,
            'vid' => $vidNumber,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => '10.20.'.($vidNumber - 300).'.0/24',
            'gateway_ip' => '10.20.'.($vidNumber - 300).'.1',
            'pool_start_ip' => '10.20.'.($vidNumber - 300).'.10',
            'pool_end_ip' => '10.20.'.($vidNumber - 300).'.50',
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 20,
            'sync_source' => 'manual',
            'status' => VidStatus::Available->value,
        ]);
    }

    private function sequenceDigit(string $suffix): int
    {
        return max(1, ord($suffix[0]) - 64);
    }
}
