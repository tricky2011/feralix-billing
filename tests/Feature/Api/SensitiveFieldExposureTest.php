<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\HotspotExpiredMode;
use App\Enums\HotspotProfileUserLock;
use App\Enums\HotspotProfileValidityMode;
use App\Enums\ResellerStatus;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\VidStatus;
use App\Enums\VidType;
use App\Models\Customer;
use App\Models\HotspotProfile;
use App\Models\Olt;
use App\Models\Ont;
use App\Models\Package;
use App\Models\Reseller;
use App\Models\Router;
use App\Models\Service;
use App\Models\Vid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensitiveFieldExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_router_endpoint_masks_api_credentials(): void
    {
        $router = Router::query()->create([
            'router_code' => 'RTR-SAFE-001',
            'router_name' => 'Router Safe 001',
            'router_role' => 'bng',
            'mgmt_ip' => '10.50.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'super-secret-password',
            'location_name' => 'HQ',
            'is_active' => true,
        ]);

        $this->getJson("/api/v1/admin/routers/{$router->id}")
            ->assertOk()
            ->assertJsonPath('data.has_api_username', true)
            ->assertJsonPath('data.api_username_masked', 'ad****')
            ->assertJsonMissingPath('data.api_username')
            ->assertJsonPath('data.has_api_password', true)
            ->assertJsonMissingPath('data.api_password');
    }

    public function test_service_and_ont_endpoints_hide_sensitive_access_fields(): void
    {
        $customer = Customer::query()->create([
            'customer_code' => 'CUST-SAFE-001',
            'full_name' => 'Customer Safe',
            'phone' => '081234567890',
            'address' => 'Jl. Aman No. 1',
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);

        $package = Package::query()->create([
            'package_name' => 'Package Safe',
            'monthly_price' => 150000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        $router = Router::query()->create([
            'router_code' => 'RTR-SAFE-002',
            'router_name' => 'Router Safe 002',
            'router_role' => 'bng',
            'mgmt_ip' => '10.50.0.2',
            'api_port' => 8728,
            'api_username' => 'operator',
            'api_password' => 'another-secret',
            'location_name' => 'HQ',
            'is_active' => true,
        ]);

        $olt = Olt::query()->create([
            'olt_code' => 'OLT-SAFE-001',
            'olt_name' => 'OLT Safe 001',
            'mgmt_ip' => '10.60.0.1',
            'vendor_name' => 'Huawei',
            'location_name' => 'POP A',
            'is_active' => true,
        ]);

        $ont = Ont::query()->create([
            'olt_id' => $olt->id,
            'ont_sn' => 'ONT-SAFE-001',
            'ont_name' => 'ONT Safe 001',
            'pon_port' => '1/1/1',
            'onu_id' => 1,
            'ssid_name' => 'SAFE-WIFI',
            'wifi_password' => 'wifi-secret',
            'status' => 'online',
        ]);

        $vid = Vid::query()->create([
            'router_id' => $router->id,
            'vid' => 310,
            'vid_type' => VidType::CustomerInternet->value,
            'subnet_cidr' => '10.70.0.0/29',
            'gateway_ip' => '10.70.0.1',
            'pool_start_ip' => '10.70.0.2',
            'pool_end_ip' => '10.70.0.6',
            'pool_ip_count' => 5,
            'rate_limit_mbps' => 20,
            'sync_source' => 'test',
            'status' => VidStatus::Assigned->value,
            'customer_id' => $customer->id,
        ]);

        $service = Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'olt_id' => $olt->id,
            'ont_id' => $ont->id,
            'vid_id' => $vid->id,
            'service_code' => 'SVC-SAFE-001',
            'monitor_vid' => 100,
            'monitor_pppoe_username' => 'monitor.safe.001',
            'monitor_pppoe_password' => 'monitor-secret',
            'pppoe_username' => 'pppoe.safe.001',
            'pppoe_password' => 'pppoe-secret',
            'internet_vid' => 310,
            'subnet_cidr' => '10.70.0.0/29',
            'gateway_ip' => '10.70.0.1',
            'dhcp_pool_start' => '10.70.0.2',
            'dhcp_pool_end' => '10.70.0.6',
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'SAFE-LIST',
            'billing_status' => ServiceBillingStatus::Paid->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-24',
        ]);

        $vid->update(['service_id' => $service->id]);

        $this->getJson("/api/v1/admin/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('data.has_monitor_pppoe_password', true)
            ->assertJsonPath('data.has_pppoe_password', true)
            ->assertJsonMissingPath('data.monitor_pppoe_password')
            ->assertJsonMissingPath('data.pppoe_password');

        $this->getJson("/api/v1/admin/onts/{$ont->id}")
            ->assertOk()
            ->assertJsonPath('data.has_wifi_password', true)
            ->assertJsonMissingPath('data.wifi_password');
    }

    public function test_voucher_batch_response_masks_generated_passwords(): void
    {
        $reseller = Reseller::query()->create([
            'reseller_code' => 'RSL-SAFE-001',
            'full_name' => 'Reseller Safe',
            'phone' => '081200000000',
            'balance' => '50000.00',
            'status' => ResellerStatus::Active->value,
        ]);

        $profile = HotspotProfile::query()->create([
            'profile_name' => 'Profile Safe',
            'validity_mode' => HotspotProfileValidityMode::DaysAfterFirstLogin->value,
            'validity_days' => 7,
            'data_limit_bytes' => null,
            'price' => '10000.00',
            'selling_price' => '15000.00',
            'user_lock' => HotspotProfileUserLock::Mac->value,
            'expired_mode' => HotspotExpiredMode::Time->value,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/voucher-batches', [
            'reseller_id' => $reseller->id,
            'hotspot_profile_id' => $profile->id,
            'total_vouchers' => 1,
            'password_mode' => 'same_as_username',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.vouchers.0.has_password', true)
            ->assertJsonMissingPath('data.vouchers.0.password');

        $passwordMasked = $response->json('data.vouchers.0.password_masked');

        $this->assertIsString($passwordMasked);
        $this->assertMatchesRegularExpression('/^\*+$/', $passwordMasked);
    }
}
