<?php

namespace Tests\Feature\Console;

use App\Models\Olt;
use App\Models\Ont;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncGenieAcsOntCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-22 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_syncs_ont_fields_from_the_fake_genieacs_provider(): void
    {
        config()->set('genieacs.sync.provider', 'fake');
        config()->set('genieacs.providers.fake.onts', [
            'ONT-SN-SYNC-001' => [
                'device_id' => 'genie-sync-001',
                'status' => 'online',
                'optical_status' => 'normal',
                'optical_info' => 'RX -21.80 dBm | TX 2.20 dBm',
                'rx_power' => '-21.80 dBm',
                'tx_power' => '2.20 dBm',
                'ssid_name' => 'Feralix-Rumah',
                'wifi_password' => 'wifi-aman',
                'last_inform_at' => '2026-04-22 09:58:00',
            ],
        ]);

        $olt = $this->createOlt();
        $ont = Ont::query()->create([
            'olt_id' => $olt->id,
            'ont_sn' => 'ONT-SN-SYNC-001',
            'ont_name' => 'ONT Sync 001',
            'pon_port' => '1/1/1',
            'onu_id' => 1,
            'status' => 'unknown',
        ]);

        $this->artisan('genieacs:sync-onts')
            ->assertSuccessful()
            ->expectsOutputToContain('checked 1 ONT(s), updated 1, unchanged 0, not found 0, failed 0');

        $this->assertDatabaseHas('onts', [
            'id' => $ont->id,
            'genieacs_device_id' => 'genie-sync-001',
            'status' => 'online',
            'optical_status' => 'normal',
            'optical_info' => 'RX -21.80 dBm | TX 2.20 dBm',
            'rx_power' => -21.8,
            'tx_power' => 2.2,
            'ssid_name' => 'Feralix-Rumah',
            'wifi_password' => 'wifi-aman',
            'last_seen_at' => '2026-04-22 09:58:00',
            'last_inform_at' => '2026-04-22 09:58:00',
            'genieacs_last_synced_at' => '2026-04-22 10:00:00',
        ]);
    }

    public function test_it_marks_missing_genieacs_records_as_not_found_without_wiping_existing_data(): void
    {
        config()->set('genieacs.sync.provider', 'fake');
        config()->set('genieacs.providers.fake.onts', []);

        $olt = $this->createOlt('OLT-GEN-002');
        $ont = Ont::query()->create([
            'olt_id' => $olt->id,
            'ont_sn' => 'ONT-SN-MISSING-001',
            'ont_name' => 'ONT Missing 001',
            'pon_port' => '1/1/2',
            'onu_id' => 2,
            'ssid_name' => 'SSID-LAMA',
            'wifi_password' => 'password-lama',
            'status' => 'online',
            'genieacs_device_id' => 'device-lama',
        ]);

        $this->artisan('genieacs:sync-onts', ['ont' => $ont->id])
            ->assertSuccessful()
            ->expectsOutputToContain('Result: not_found');

        $this->assertDatabaseHas('onts', [
            'id' => $ont->id,
            'ssid_name' => 'SSID-LAMA',
            'wifi_password' => 'password-lama',
            'status' => 'online',
            'genieacs_device_id' => 'device-lama',
            'genieacs_last_synced_at' => null,
        ]);
    }

    private function createOlt(string $oltCode = 'OLT-GEN-001'): Olt
    {
        return Olt::query()->create([
            'olt_code' => $oltCode,
            'olt_name' => 'OLT '.$oltCode,
            'mgmt_ip' => '10.10.10.1',
            'vendor_name' => 'ZTE',
            'location_name' => 'POP GenieACS',
            'is_active' => true,
        ]);
    }
}
