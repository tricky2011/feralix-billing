<?php

namespace Tests\Feature\Api;

use App\Enums\VidStatus;
use App\Enums\VidType;
use App\Models\Location;
use App\Models\Olt;
use App\Models\Ont;
use App\Models\Package;
use App\Models\Router;
use App\Models\RouterScope;
use App\Models\Vid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemainingApiResponseStandardizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_endpoint_uses_standard_success_envelope(): void
    {
        [$location, $olt, $router, $scope, $package] = $this->seedInventory();
        $vid = $this->createVid($router, $scope);

        $this->getJson('/api/v1/admin/customer-references')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Customer references retrieved successfully.')
            ->assertJsonPath('data.locations.0.id', $location->id)
            ->assertJsonPath('data.olts.0.id', $olt->id)
            ->assertJsonPath('data.routers.0.id', $router->id)
            ->assertJsonPath('data.packages.0.id', $package->id)
            ->assertJsonPath('data.available_vids.0.id', $vid->id)
            ->assertJsonPath('meta', []);
    }

    public function test_master_data_lists_use_standard_pagination_meta(): void
    {
        [$location, $olt, $router, $scope, $package] = $this->seedInventory();
        $ont = $this->createOnt($olt);

        $this->getJson('/api/v1/admin/locations?search=Site')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $location->id)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('meta.filters.search', 'Site');

        $this->getJson('/api/v1/admin/packages?search=Starter')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $package->id)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);

        $this->getJson('/api/v1/admin/olts?search=OLT')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $olt->id)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);

        $this->getJson('/api/v1/admin/onts?search=ONT')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $ont->id)
            ->assertJsonPath('data.0.has_wifi_password', true)
            ->assertJsonMissingPath('data.0.wifi_password')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);

        $this->getJson('/api/v1/admin/router-scopes?search=Main')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $scope->id)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_master_data_detail_endpoints_use_standard_success_envelope(): void
    {
        [$location, $olt, $router, $scope, $package] = $this->seedInventory();
        $ont = $this->createOnt($olt);

        $this->getJson("/api/v1/admin/locations/{$location->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $location->id)
            ->assertJsonPath('meta', []);

        $this->getJson("/api/v1/admin/packages/{$package->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $package->id)
            ->assertJsonPath('meta', []);

        $this->getJson("/api/v1/admin/olts/{$olt->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $olt->id)
            ->assertJsonPath('meta', []);

        $this->getJson("/api/v1/admin/onts/{$ont->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $ont->id)
            ->assertJsonMissingPath('data.wifi_password')
            ->assertJsonPath('meta', []);

        $this->getJson("/api/v1/admin/router-scopes/{$scope->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $scope->id)
            ->assertJsonPath('meta', []);
    }

    public function test_hotspot_radius_internal_endpoint_uses_standard_success_envelope(): void
    {
        config()->set('hotspot.radius.internal_secret', '');

        $this->postJson('/api/v1/internal/hotspot-radius/accounting', [
            'username' => 'missing-voucher',
            'acct_status_type' => 'start',
            'acct_session_id' => 'STD-001',
            'calling_station_id' => 'AA-BB-CC-DD-EE-FF',
            'nas_identifier' => 'AP-STANDARD',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Hotspot RADIUS accounting simulated successfully.')
            ->assertJsonPath('meta.provider', 'stub')
            ->assertJsonPath('data.processed', false)
            ->assertJsonPath('meta.provider', 'stub');
    }

    private function seedInventory(): array
    {
        $location = Location::query()->create([
            'location_code' => 'LOC-STD',
            'location_name' => 'Site Standard',
            'address' => 'Jl. Standard No. 1',
            'is_active' => true,
        ]);

        $olt = Olt::query()->create([
            'olt_code' => 'OLT-STD',
            'olt_name' => 'OLT Standard',
            'mgmt_ip' => '172.16.10.1',
            'vendor_name' => 'Huawei',
            'location_id' => $location->id,
            'location_name' => $location->location_name,
            'is_active' => true,
        ]);

        $router = Router::query()->create([
            'router_code' => 'RTR-STD',
            'router_name' => 'Router Standard',
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.10.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'router-secret',
            'location_name' => $location->location_name,
            'is_active' => true,
        ]);

        $scope = RouterScope::query()->create([
            'router_id' => $router->id,
            'scope_name' => 'Main Standard Scope',
            'monitor_vid' => 100,
            'vid_start' => 300,
            'vid_end' => 399,
            'is_special' => false,
            'notes' => 'standard response test',
        ]);

        $package = Package::query()->create([
            'package_name' => 'Starter Standard 20 Mbps',
            'monthly_price' => 150000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        return [$location, $olt, $router, $scope, $package];
    }

    private function createOnt(Olt $olt): Ont
    {
        return Ont::query()->create([
            'olt_id' => $olt->id,
            'ont_sn' => 'ONT-STD-001',
            'ont_name' => 'ONT Standard',
            'pon_port' => '1/1/1',
            'onu_id' => 1,
            'ssid_name' => 'STD-WIFI',
            'wifi_password' => 'wifi-secret',
            'status' => 'online',
        ]);
    }

    private function createVid(Router $router, RouterScope $scope): Vid
    {
        return Vid::query()->create([
            'router_id' => $router->id,
            'scope_id' => $scope->id,
            'vid' => 310,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => '10.31.0.0/29',
            'gateway_ip' => '10.31.0.1',
            'pool_start_ip' => '10.31.0.2',
            'pool_end_ip' => '10.31.0.6',
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 20,
            'sync_source' => 'test',
            'status' => VidStatus::Available->value,
        ]);
    }
}
