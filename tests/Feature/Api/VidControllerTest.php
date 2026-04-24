<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\VidStatus;
use App\Enums\VidType;
use App\Models\Customer;
use App\Models\Router;
use App\Models\RouterScope;
use App\Models\Vid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VidControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_assigned_vid_record(): void
    {
        $router = $this->createRouter();
        $scope = $this->createScope($router);
        $customer = $this->createCustomer();

        $response = $this->postJson('/api/v1/admin/vids', [
            'router_id' => $router->id,
            'scope_id' => $scope->id,
            'vid' => 110,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => '10.10.110.0/29',
            'gateway_ip' => '10.10.110.1',
            'pool_start_ip' => '10.10.110.2',
            'pool_end_ip' => '10.10.110.6',
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 20,
            'sync_source' => 'internal',
            'status' => VidStatus::Assigned->value,
            'customer_id' => $customer->id,
            'service_id' => 1001,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.router_id', $router->id)
            ->assertJsonPath('data.scope_id', $scope->id)
            ->assertJsonPath('data.vid', 110)
            ->assertJsonPath('data.vid_type', VidType::CustomerInternet->value)
            ->assertJsonPath('data.status', VidStatus::Assigned->value)
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.service_id', 1001)
            ->assertJsonPath('data.scope.scope_name', $scope->scope_name)
            ->assertJsonPath('data.customer.customer_code', $customer->customer_code);

        $this->assertDatabaseHas('vids', [
            'router_id' => $router->id,
            'scope_id' => $scope->id,
            'vid' => 110,
            'status' => VidStatus::Assigned->value,
            'customer_id' => $customer->id,
            'service_id' => 1001,
        ]);
    }

    public function test_it_filters_vids_by_router_status_and_vid_type(): void
    {
        $routerA = $this->createRouter('RTR-A');
        $routerB = $this->createRouter('RTR-B');

        $match = Vid::query()->create([
            'router_id' => $routerA->id,
            'vid' => 200,
            'vid_type' => VidType::Monitoring->value,
            'status' => VidStatus::Available->value,
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 10,
        ]);

        Vid::query()->create([
            'router_id' => $routerA->id,
            'vid' => 201,
            'vid_type' => VidType::CustomerInternet->value,
            'status' => VidStatus::Assigned->value,
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 10,
        ]);

        Vid::query()->create([
            'router_id' => $routerB->id,
            'vid' => 300,
            'vid_type' => VidType::Monitoring->value,
            'status' => VidStatus::Available->value,
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 10,
        ]);

        $response = $this->getJson(sprintf(
            '/api/v1/admin/vids?router_id=%d&status=%s&vid_type=%s',
            $routerA->id,
            VidStatus::Available->value,
            VidType::Monitoring->value,
        ));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('meta.filters.router_id', (string) $routerA->id)
            ->assertJsonPath('meta.filters.status', VidStatus::Available->value)
            ->assertJsonPath('meta.filters.vid_type', VidType::Monitoring->value);
    }

    public function test_it_rejects_duplicate_vid_for_the_same_router(): void
    {
        $router = $this->createRouter();

        Vid::query()->create([
            'router_id' => $router->id,
            'vid' => 120,
            'vid_type' => VidType::Monitoring->value,
            'status' => VidStatus::Available->value,
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 10,
        ]);

        $response = $this->postJson('/api/v1/admin/vids', [
            'router_id' => $router->id,
            'vid' => 120,
            'vid_type' => VidType::CustomerInternet->value,
            'status' => VidStatus::Available->value,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vid']);
    }

    public function test_it_clears_assignment_fields_when_status_is_not_assigned(): void
    {
        $router = $this->createRouter();
        $customer = $this->createCustomer();
        $vid = Vid::query()->create([
            'router_id' => $router->id,
            'vid' => 210,
            'vid_type' => VidType::CustomerInternet->value,
            'status' => VidStatus::Assigned->value,
            'customer_id' => $customer->id,
            'service_id' => 2001,
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 10,
        ]);

        $response = $this->putJson("/api/v1/admin/vids/{$vid->id}", [
            'router_id' => $router->id,
            'scope_id' => null,
            'vid' => 210,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => null,
            'gateway_ip' => null,
            'pool_start_ip' => null,
            'pool_end_ip' => null,
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 10,
            'sync_source' => null,
            'status' => VidStatus::Available->value,
            'customer_id' => $customer->id,
            'service_id' => 2001,
            'last_synced_at' => null,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', VidStatus::Available->value)
            ->assertJsonPath('data.customer_id', null)
            ->assertJsonPath('data.service_id', null);

        $this->assertDatabaseHas('vids', [
            'id' => $vid->id,
            'status' => VidStatus::Available->value,
            'customer_id' => null,
            'service_id' => null,
        ]);
    }

    private function createRouter(string $routerCode = 'RTR-1'): Router
    {
        return Router::query()->create([
            'router_code' => $routerCode,
            'router_name' => "Router {$routerCode}",
            'router_role' => 'bng',
            'mgmt_ip' => '10.0.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'location_name' => 'HQ',
            'is_active' => true,
        ]);
    }

    private function createScope(Router $router, string $scopeName = 'Main Scope'): RouterScope
    {
        return RouterScope::query()->create([
            'router_id' => $router->id,
            'scope_name' => $scopeName,
            'monitor_vid' => 100,
            'vid_start' => 100,
            'vid_end' => 200,
            'is_special' => false,
            'notes' => null,
        ]);
    }

    private function createCustomer(string $customerCode = 'CUST-001'): Customer
    {
        return Customer::query()->create([
            'customer_code' => $customerCode,
            'full_name' => 'Customer Test',
            'phone' => '08123456789',
            'address' => 'Jl. Testing No. 1',
            'customer_type' => CustomerType::Business->value,
            'status' => CustomerStatus::Active->value,
        ]);
    }
}
