<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Customer;
use App\Models\Olt;
use App\Models\Ont;
use App\Models\Router;
use App\Models\Technician;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-22 10:15:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_a_new_install_work_order_with_generated_number_and_assigned_status(): void
    {
        $customer = $this->createCustomer();
        $router = $this->createRouter();
        $olt = $this->createOlt();
        $ont = $this->createOnt($olt);
        $technician = $this->createTechnician();

        $response = $this->postJson('/api/v1/admin/work-orders', [
            'customer_id' => $customer->id,
            'router_id' => $router->id,
            'olt_id' => $olt->id,
            'ont_id' => $ont->id,
            'wo_type' => WorkOrderType::NewInstall->value,
            'assigned_technician_id' => $technician->id,
            'planned_monitor_vid' => 100,
            'planned_internet_vid' => 310,
            'planned_subnet_cidr' => '10.10.10.0/29',
            'work_notes' => 'Pemasangan baru untuk pelanggan FTTH.',
            'scheduled_at' => '2026-04-23 09:00:00',
        ]);

        $workOrderId = $response->json('data.id');
        $workOrderNumber = (string) $response->json('data.wo_number');

        $response
            ->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.router_id', $router->id)
            ->assertJsonPath('data.olt_id', $olt->id)
            ->assertJsonPath('data.ont_id', $ont->id)
            ->assertJsonPath('data.wo_type', WorkOrderType::NewInstall->value)
            ->assertJsonPath('data.assigned_technician_id', $technician->id)
            ->assertJsonPath('data.status', WorkOrderStatus::Assigned->value)
            ->assertJsonPath('data.assigned_technician.technician_code', $technician->technician_code)
            ->assertJsonPath('data.completed_at', null);

        $this->assertStringStartsWith('WO-20260422-', $workOrderNumber);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrderId,
            'customer_id' => $customer->id,
            'router_id' => $router->id,
            'olt_id' => $olt->id,
            'ont_id' => $ont->id,
            'wo_number' => $workOrderNumber,
            'wo_type' => WorkOrderType::NewInstall->value,
            'assigned_technician_id' => $technician->id,
            'status' => WorkOrderStatus::Assigned->value,
        ]);
    }

    public function test_it_filters_work_orders_by_status_and_assigned_technician(): void
    {
        $router = $this->createRouter();
        $firstTechnician = $this->createTechnician('TECH-WO-001', 'Teknisi WO A');
        $secondTechnician = $this->createTechnician('TECH-WO-002', 'Teknisi WO B');

        $matchingWorkOrder = WorkOrder::query()->create([
            'router_id' => $router->id,
            'wo_number' => 'WO-20260422-AAAAAA',
            'wo_type' => WorkOrderType::Relocation->value,
            'assigned_technician_id' => $firstTechnician->id,
            'status' => WorkOrderStatus::Assigned->value,
            'work_notes' => 'Relokasi pelanggan cluster A.',
        ]);

        WorkOrder::query()->create([
            'router_id' => $router->id,
            'wo_number' => 'WO-20260422-BBBBBB',
            'wo_type' => WorkOrderType::Termination->value,
            'assigned_technician_id' => $secondTechnician->id,
            'status' => WorkOrderStatus::InProgress->value,
            'work_notes' => 'Terminasi layanan pelanggan cluster B.',
        ]);

        $response = $this->getJson(sprintf(
            '/api/v1/admin/work-orders?status=%s&assigned_technician_id=%d',
            WorkOrderStatus::Assigned->value,
            $firstTechnician->id
        ));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingWorkOrder->id)
            ->assertJsonPath('data.0.status', WorkOrderStatus::Assigned->value)
            ->assertJsonPath('data.0.assigned_technician_id', $firstTechnician->id);
    }

    public function test_it_sets_completed_at_when_a_work_order_is_completed(): void
    {
        $customer = $this->createCustomer('CUST-WO-002', 'Pelanggan Selesai');
        $router = $this->createRouter('RTR-WO-002');
        $technician = $this->createTechnician('TECH-WO-003', 'Teknisi Selesai');
        $workOrder = WorkOrder::query()->create([
            'customer_id' => $customer->id,
            'router_id' => $router->id,
            'wo_number' => 'WO-20260422-CCCCCC',
            'wo_type' => WorkOrderType::OntReplacement->value,
            'assigned_technician_id' => $technician->id,
            'planned_monitor_vid' => 101,
            'planned_internet_vid' => 311,
            'planned_subnet_cidr' => '10.10.20.0/29',
            'status' => WorkOrderStatus::InProgress->value,
            'work_notes' => 'Penggantian ONT di lokasi pelanggan.',
            'scheduled_at' => '2026-04-22 11:00:00',
        ]);

        $response = $this->putJson("/api/v1/admin/work-orders/{$workOrder->id}", [
            'customer_id' => $customer->id,
            'router_id' => $router->id,
            'wo_type' => WorkOrderType::OntReplacement->value,
            'assigned_technician_id' => $technician->id,
            'planned_monitor_vid' => 101,
            'planned_internet_vid' => 311,
            'planned_subnet_cidr' => '10.10.20.0/29',
            'status' => WorkOrderStatus::Completed->value,
            'work_notes' => 'ONT berhasil diganti dan link up.',
            'scheduled_at' => '2026-04-22 11:00:00',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', WorkOrderStatus::Completed->value)
            ->assertJsonPath('data.completed_at', Carbon::now()->toISOString());

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'status' => WorkOrderStatus::Completed->value,
        ]);

        $this->assertNotNull($workOrder->fresh()->completed_at);
    }

    private function createCustomer(
        string $customerCode = 'CUST-WO-001',
        string $fullName = 'Pelanggan Work Order',
    ): Customer {
        return Customer::query()->create([
            'customer_code' => $customerCode,
            'full_name' => $fullName,
            'phone' => '081234567890',
            'address' => 'Jl. Work Order No. 1',
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);
    }

    private function createRouter(string $routerCode = 'RTR-WO-001'): Router
    {
        return Router::query()->create([
            'router_code' => $routerCode,
            'router_name' => 'Router Work Order',
            'router_role' => 'edge',
            'mgmt_ip' => '10.0.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'location_name' => 'NOC',
            'is_active' => true,
        ]);
    }

    private function createOlt(string $oltCode = 'OLT-WO-001'): Olt
    {
        return Olt::query()->create([
            'olt_code' => $oltCode,
            'olt_name' => 'OLT Work Order',
            'mgmt_ip' => '10.0.1.1',
            'vendor_name' => 'ZTE',
            'location_name' => 'POP A',
            'is_active' => true,
        ]);
    }

    private function createOnt(Olt $olt, string $ontSn = 'ONT-SN-WO-001'): Ont
    {
        return Ont::query()->create([
            'olt_id' => $olt->id,
            'ont_sn' => $ontSn,
            'ont_name' => 'ONT Work Order',
            'pon_port' => '1/1/1',
            'onu_id' => 1,
            'status' => 'online',
        ]);
    }

    private function createTechnician(
        string $technicianCode = 'TECH-WO-001',
        string $fullName = 'Teknisi Work Order',
    ): Technician {
        return Technician::query()->create([
            'technician_code' => $technicianCode,
            'full_name' => $fullName,
            'phone' => '081200000000',
            'is_active' => true,
        ]);
    }
}
