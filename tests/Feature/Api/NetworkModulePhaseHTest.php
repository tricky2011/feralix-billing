<?php

namespace Tests\Feature\Api;

use App\Models\NetworkLocation;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Olt;
use App\Models\Router;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkModulePhaseHTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_network_locations(): void
    {
        $createResponse = $this->postJson('/api/v1/admin/network-locations', [
            'name' => 'Central POP',
            'code' => 'LOC-CENTRAL',
            'address' => 'Jl. Fiber Utama No. 1',
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
            'description' => 'Lokasi utama distribusi.',
            'status' => 'active',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Central POP')
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $locationId = $createResponse->json('data.id');

        $this->patchJson("/api/v1/admin/network-locations/{$locationId}", [
            'name' => 'Central POP Updated',
            'code' => 'LOC-CENTRAL',
            'address' => 'Jl. Fiber Utama No. 2',
            'latitude' => -6.2010000,
            'longitude' => 106.8170000,
            'description' => 'Lokasi utama distribusi update.',
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->getJson('/api/v1/admin/network-locations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->deleteJson("/api/v1/admin/network-locations/{$locationId}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->assertDatabaseMissing('network_locations', ['id' => $locationId]);
    }

    public function test_admin_can_crud_olt(): void
    {
        $location = NetworkLocation::query()->create([
            'name' => 'Site A',
            'code' => 'SITE-A',
            'status' => 'active',
        ]);

        $createResponse = $this->postJson('/api/v1/admin/olts', [
            'name' => 'OLT A',
            'code' => 'OLT-A',
            'host' => '10.10.10.10',
            'brand' => 'Huawei',
            'model' => 'MA5800',
            'pon_ports' => 16,
            'location_id' => $location->id,
            'status' => 'active',
            'description' => 'Core OLT A',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'OLT A')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $oltId = $createResponse->json('data.id');

        $this->patchJson("/api/v1/admin/olts/{$oltId}", [
            'name' => 'OLT A Updated',
            'code' => 'OLT-A',
            'host' => '10.10.10.11',
            'brand' => 'Huawei',
            'model' => 'MA5800X',
            'pon_ports' => 32,
            'location_id' => $location->id,
            'status' => 'inactive',
            'description' => 'Core OLT A Update',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->getJson('/api/v1/admin/olts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->deleteJson("/api/v1/admin/olts/{$oltId}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->assertDatabaseMissing('olts', ['id' => $oltId]);
    }

    public function test_admin_can_crud_odc(): void
    {
        $location = NetworkLocation::query()->create([
            'name' => 'Site B',
            'code' => 'SITE-B',
            'status' => 'active',
        ]);

        $createResponse = $this->postJson('/api/v1/admin/odcs', [
            'name' => 'ODC B',
            'code' => 'ODC-B',
            'location_id' => $location->id,
            'latitude' => -6.2100000,
            'longitude' => 106.8200000,
            'capacity' => 64,
            'used_ports' => 8,
            'status' => 'active',
            'description' => 'ODC utama site B',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'ODC B')
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $odcId = $createResponse->json('data.id');

        $this->patchJson("/api/v1/admin/odcs/{$odcId}", [
            'name' => 'ODC B Updated',
            'code' => 'ODC-B',
            'location_id' => $location->id,
            'latitude' => -6.2110000,
            'longitude' => 106.8210000,
            'capacity' => 64,
            'used_ports' => 9,
            'status' => 'inactive',
            'description' => 'ODC site B update',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->getJson('/api/v1/admin/odcs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->deleteJson("/api/v1/admin/odcs/{$odcId}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->assertDatabaseMissing('odcs', ['id' => $odcId]);
    }

    public function test_admin_can_crud_odp(): void
    {
        $location = NetworkLocation::query()->create([
            'name' => 'Site C',
            'code' => 'SITE-C',
            'status' => 'active',
        ]);

        $odc = Odc::query()->create([
            'name' => 'ODC C',
            'code' => 'ODC-C',
            'location_id' => $location->id,
            'status' => 'active',
            'used_ports' => 0,
        ]);

        $olt = Olt::query()->create([
            'name' => 'OLT C',
            'code' => 'OLT-C',
            'host' => '10.10.20.10',
            'brand' => 'ZTE',
            'model' => 'C320',
            'pon_ports' => 16,
            'status' => 'active',
            'network_location_id' => $location->id,
            'description' => 'OLT C',
            'olt_code' => 'OLT-C',
            'olt_name' => 'OLT C',
            'mgmt_ip' => '10.10.20.10',
            'vendor_name' => 'ZTE',
            'is_active' => true,
        ]);

        $createResponse = $this->postJson('/api/v1/admin/odps', [
            'name' => 'ODP C',
            'code' => 'ODP-C',
            'odc_id' => $odc->id,
            'olt_id' => $olt->id,
            'location_id' => $location->id,
            'latitude' => -6.2200000,
            'longitude' => 106.8300000,
            'capacity' => 16,
            'used_ports' => 2,
            'status' => 'active',
            'description' => 'ODP utama site C',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'ODP C')
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $odpId = $createResponse->json('data.id');

        $this->patchJson("/api/v1/admin/odps/{$odpId}", [
            'name' => 'ODP C Updated',
            'code' => 'ODP-C',
            'odc_id' => $odc->id,
            'olt_id' => $olt->id,
            'location_id' => $location->id,
            'latitude' => -6.2210000,
            'longitude' => 106.8310000,
            'capacity' => 16,
            'used_ports' => 3,
            'status' => 'inactive',
            'description' => 'ODP site C update',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->getJson('/api/v1/admin/odps')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->deleteJson("/api/v1/admin/odps/{$odpId}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $this->assertDatabaseMissing('odps', ['id' => $odpId]);
    }

    public function test_unique_code_validation_works(): void
    {
        NetworkLocation::query()->create([
            'name' => 'Site Dup',
            'code' => 'SITE-DUP',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/admin/network-locations', [
            'name' => 'Site Dup 2',
            'code' => 'SITE-DUP',
            'status' => 'active',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['code'])
            ->assertJsonStructure(['success', 'message', 'errors', 'meta']);
    }

    public function test_invalid_coordinate_is_rejected(): void
    {
        $this->postJson('/api/v1/admin/network-locations', [
            'name' => 'Invalid Coordinate',
            'code' => 'LOC-INVALID',
            'latitude' => 91,
            'longitude' => 181,
            'status' => 'active',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['latitude', 'longitude'])
            ->assertJsonStructure(['success', 'message', 'errors', 'meta']);
    }

    public function test_fiber_map_endpoint_returns_placeholder_true(): void
    {
        $this->getJson('/api/v1/admin/fiber-map')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Fiber network map placeholder retrieved successfully.')
            ->assertJsonPath('data.placeholder', true)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);
    }

    public function test_unauthorized_user_is_rejected_for_network_module_endpoints(): void
    {
        $technician = User::factory()->technician()->create();
        $this->authenticateApi($technician);

        $this->getJson('/api/v1/admin/network-locations')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors', 'meta']);
    }

    public function test_olt_code_unique_per_router_not_globally(): void
    {
        $location = NetworkLocation::query()->create(['name' => 'Site X', 'code' => 'SITE-X', 'status' => 'active']);

        $routerA = Router::query()->create([
            'router_code' => 'RTR-A', 'router_name' => 'Router A', 'router_role' => 'bng',
            'mgmt_ip' => '10.0.0.1', 'api_port' => 8728, 'api_username' => 'admin', 'api_password' => 'secret', 'is_active' => true,
        ]);
        $routerB = Router::query()->create([
            'router_code' => 'RTR-B', 'router_name' => 'Router B', 'router_role' => 'bng',
            'mgmt_ip' => '10.0.0.2', 'api_port' => 8728, 'api_username' => 'admin', 'api_password' => 'secret', 'is_active' => true,
        ]);

        // Create OLT001 on Router A — harus berhasil
        $this->postJson('/api/v1/admin/olts', [
            'name' => 'OLT Router A', 'code' => 'OLT001',
            'location_id' => $location->id, 'router_id' => $routerA->id, 'status' => 'active',
        ])->assertCreated()->assertJsonPath('success', true);

        // Create OLT001 pada Router yang SAMA — harus gagal (duplicate)
        $this->postJson('/api/v1/admin/olts', [
            'name' => 'OLT Router A Dup', 'code' => 'OLT001',
            'location_id' => $location->id, 'router_id' => $routerA->id, 'status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);

        // Create OLT001 pada Router BERBEDA (Router B) — harus berhasil
        $this->postJson('/api/v1/admin/olts', [
            'name' => 'OLT Router B', 'code' => 'OLT001',
            'location_id' => $location->id, 'router_id' => $routerB->id, 'status' => 'active',
        ])->assertCreated()->assertJsonPath('success', true);
    }

    public function test_network_module_endpoints_keep_standard_success_envelope(): void
    {
        $endpoints = [
            '/api/v1/admin/network-locations',
            '/api/v1/admin/olts',
            '/api/v1/admin/odcs',
            '/api/v1/admin/odps',
            '/api/v1/admin/fiber-map',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)
                ->assertOk()
                ->assertJsonStructure(['success', 'message', 'data', 'meta']);
        }
    }
}
