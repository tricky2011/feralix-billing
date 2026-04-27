<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\TicketAssignmentMode;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\Service;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TechnicianDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipApiAuthentication = true;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-27 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_access_technician_dashboard(): void
    {
        $router = $this->createRouter('A');
        $technician = $this->createTechnician('TECH-TD-01', 'Teknisi Admin Access');
        $service = $this->createService('A', $router);

        $this->createWorkOrder('A1', $service, $router, $technician, WorkOrderStatus::Completed);
        $this->createTicket('A1', $service, $technician, TicketStatus::Closed);

        $admin = User::factory()->admin()->create();
        $admin->accessibleRouters()->attach($router->id);

        $this->authenticateApi($admin);

        $response = $this->getJson('/api/v1/admin/technician-dashboard?period=month');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Technician dashboard retrieved successfully.')
            ->assertJsonPath('data.summary.completed_installations', 1)
            ->assertJsonPath('data.summary.closed_tickets', 1);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'technician_dashboard.viewed',
            'module' => 'technician_dashboard',
        ]);
    }

    public function test_technician_can_access_technician_dashboard_and_is_scoped_to_own_id(): void
    {
        $router = $this->createRouter('B');
        $ownTechnician = $this->createTechnician('TECH-TD-02', 'Teknisi Sendiri');
        $otherTechnician = $this->createTechnician('TECH-TD-03', 'Teknisi Lain');

        $serviceA = $this->createService('B1', $router);
        $serviceB = $this->createService('B2', $router);

        $this->createWorkOrder('B1', $serviceA, $router, $ownTechnician, WorkOrderStatus::Completed);
        $this->createWorkOrder('B2', $serviceB, $router, $otherTechnician, WorkOrderStatus::Completed);
        $this->createTicket('B1', $serviceA, $ownTechnician, TicketStatus::Resolved);
        $this->createTicket('B2', $serviceB, $otherTechnician, TicketStatus::Closed);

        $technicianUser = User::factory()->technician($ownTechnician->id)->create();

        $this->authenticateApi($technicianUser);

        $response = $this->getJson('/api/v1/admin/technician-dashboard?period=month&technician_id='.$otherTechnician->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.filters.technician_id', $ownTechnician->id);

        $workOrderTechnicianIds = collect($response->json('data.work_orders'))
            ->pluck('assigned_technician_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $ticketTechnicianIds = collect($response->json('data.tickets'))
            ->pluck('assigned_technician_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->assertSame([$ownTechnician->id], $workOrderTechnicianIds);
        $this->assertSame([$ownTechnician->id], $ticketTechnicianIds);
    }

    public function test_non_admin_and_non_technician_user_is_denied(): void
    {
        $reseller = User::factory()->reseller()->create();

        $this->authenticateApi($reseller);

        $this->getJson('/api/v1/admin/technician-dashboard')
            ->assertForbidden();
    }

    public function test_dashboard_filter_technician_id_works(): void
    {
        $router = $this->createRouter('C');
        $technicianA = $this->createTechnician('TECH-TD-04', 'Teknisi Filter A');
        $technicianB = $this->createTechnician('TECH-TD-05', 'Teknisi Filter B');

        $serviceA = $this->createService('C1', $router);
        $serviceB = $this->createService('C2', $router);

        $this->createWorkOrder('C1', $serviceA, $router, $technicianA, WorkOrderStatus::Completed);
        $this->createWorkOrder('C2', $serviceA, $router, $technicianA, WorkOrderStatus::Open);
        $this->createWorkOrder('C3', $serviceB, $router, $technicianB, WorkOrderStatus::Completed);

        $this->createTicket('C1', $serviceA, $technicianA, TicketStatus::Open);
        $this->createTicket('C2', $serviceA, $technicianA, TicketStatus::Closed);
        $this->createTicket('C3', $serviceB, $technicianB, TicketStatus::Closed);

        $superadmin = User::factory()->superadmin()->create();
        $this->authenticateApi($superadmin);

        $response = $this->getJson('/api/v1/admin/technician-dashboard?period=month&technician_id='.$technicianA->id);

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.total_installations', 2)
            ->assertJsonPath('data.summary.completed_installations', 1)
            ->assertJsonPath('data.summary.open_tickets', 1)
            ->assertJsonPath('data.summary.closed_tickets', 1)
            ->assertJsonPath('data.summary.total_points', 15);

        $workOrderTechnicianIds = collect($response->json('data.work_orders'))
            ->pluck('assigned_technician_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $ticketTechnicianIds = collect($response->json('data.tickets'))
            ->pluck('assigned_technician_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->assertSame([$technicianA->id], $workOrderTechnicianIds);
        $this->assertSame([$technicianA->id], $ticketTechnicianIds);
    }

    public function test_dashboard_response_contains_required_sections_with_standard_envelope(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $this->authenticateApi($superadmin);

        $response = $this->getJson('/api/v1/admin/technician-dashboard');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'filters',
                    'summary' => [
                        'total_installations',
                        'completed_installations',
                        'open_tickets',
                        'closed_tickets',
                        'total_points',
                    ],
                    'targets' => [
                        'installation_target',
                        'ticket_target',
                    ],
                    'ranking',
                    'work_orders',
                    'tickets',
                    'point_rules',
                ],
                'meta',
            ])
            ->assertJsonPath('success', true);
    }

    public function test_export_pdf_placeholder_returns_success_and_records_audit_log(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $this->authenticateApi($superadmin);

        $response = $this->postJson('/api/v1/admin/technician-dashboard/export-pdf');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Export PDF belum tersedia.')
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta', []);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $superadmin->id,
            'action' => 'technician_dashboard.export_pdf_requested',
            'module' => 'technician_dashboard',
        ]);
    }

    private function createRouter(string $suffix): Router
    {
        $digit = $this->sequenceDigit($suffix);

        return Router::query()->create([
            'router_code' => 'RTR-TD-'.$suffix,
            'router_name' => 'Router TD '.$suffix,
            'router_role' => 'bng',
            'mgmt_ip' => '10.77.'.$digit.'.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'location_name' => 'POP TD '.$suffix,
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

    private function createService(string $suffix, Router $router): Service
    {
        $digit = $this->sequenceDigit($suffix);

        $customer = Customer::query()->create([
            'customer_code' => 'CUST-TD-'.$suffix,
            'full_name' => 'Customer TD '.$suffix,
            'phone' => '08123'.$digit.'56789',
            'address' => 'Alamat TD '.$suffix,
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);

        $package = Package::query()->create([
            'package_name' => 'Package TD '.$suffix,
            'monthly_price' => 150000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        return Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'service_code' => 'SVC-TD-'.$suffix,
            'monitor_vid' => 100 + $digit,
            'monitor_pppoe_username' => 'monitor.td.'.$suffix,
            'monitor_pppoe_password' => 'secret-monitor',
            'internet_vid' => 200 + $digit,
            'subnet_cidr' => '10.90.'.$digit.'.0/24',
            'gateway_ip' => '10.90.'.$digit.'.1',
            'dhcp_pool_start' => '10.90.'.$digit.'.10',
            'dhcp_pool_end' => '10.90.'.$digit.'.50',
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'ADDR-TD-'.$suffix,
            'billing_status' => ServiceBillingStatus::Pending->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-01',
        ]);
    }

    private function createWorkOrder(
        string $suffix,
        Service $service,
        Router $router,
        Technician $technician,
        WorkOrderStatus $status,
    ): WorkOrder {
        return WorkOrder::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'router_id' => $router->id,
            'wo_number' => 'WO-TD-'.$suffix,
            'wo_type' => WorkOrderType::NewInstall->value,
            'assigned_technician_id' => $technician->id,
            'planned_monitor_vid' => $service->monitor_vid,
            'planned_internet_vid' => $service->internet_vid,
            'planned_subnet_cidr' => $service->subnet_cidr,
            'status' => $status->value,
            'work_notes' => 'Work order TD '.$suffix,
            'completed_at' => $status === WorkOrderStatus::Completed ? now() : null,
        ]);
    }

    private function createTicket(
        string $suffix,
        Service $service,
        Technician $technician,
        TicketStatus $status,
    ): Ticket {
        return Ticket::query()->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'ticket_number' => 'TCK-TD-'.$suffix,
            'category' => 'network_issue',
            'priority' => TicketPriority::Medium->value,
            'description' => 'Ticket TD '.$suffix,
            'assigned_technician_id' => $technician->id,
            'assignment_mode' => TicketAssignmentMode::Manual->value,
            'status' => $status->value,
        ]);
    }

    private function sequenceDigit(string $suffix): int
    {
        $firstChar = strtoupper((string) $suffix[0]);

        if (ctype_alpha($firstChar)) {
            return max(1, ord($firstChar) - 64);
        }

        return max(1, (int) preg_replace('/[^0-9]/', '', $suffix));
    }
}
