<?php

namespace Tests\Feature\Api;

use App\Enums\HotspotExpiredMode;
use App\Enums\HotspotProfileUserLock;
use App\Enums\HotspotProfileValidityMode;
use App\Enums\HotspotRadiusEventType;
use App\Enums\HotspotVoucherStatus;
use App\Enums\ResellerStatus;
use App\Models\HotspotProfile;
use App\Models\HotspotVoucher;
use App\Models\Reseller;
use App\Models\VoucherBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HotspotRadiusIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-22 09:00:00');

        config()->set('hotspot.radius.internal_secret', 'radius-secret');
        config()->set('hotspot.radius.expired_redirect_url', 'https://landing.example/expired?voucher={username}&reason={reason}');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_authorizes_first_login_locks_mac_and_sets_expiry(): void
    {
        $voucher = $this->createVoucher(password: 'radius-pass');

        $response = $this->postJson('/api/v1/internal/hotspot-radius/authorize', [
            'username' => $voucher->username,
            'password' => 'radius-pass',
            'calling_station_id' => 'AA-BB-CC-DD-EE-FF',
            'nas_identifier' => 'AP-HOTSPOT-01',
            'requested_at' => '2026-04-22 10:30:00',
        ], $this->internalHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.provider', 'stub')
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.reason', null)
            ->assertJsonPath('data.locked_mac', 'AA:BB:CC:DD:EE:FF')
            ->assertJsonPath('data.voucher_status', HotspotVoucherStatus::Active->value)
            ->assertJsonPath('data.first_login_at', Carbon::parse('2026-04-22 10:30:00')->toISOString())
            ->assertJsonPath('data.expires_at', Carbon::parse('2026-04-29 10:30:00')->toISOString())
            ->assertJsonPath('data.radius_attributes.Session-Timeout', 604800);

        $this->assertDatabaseHas('hotspot_vouchers', [
            'id' => $voucher->id,
            'locked_mac' => 'AA:BB:CC:DD:EE:FF',
            'status' => HotspotVoucherStatus::Active->value,
        ]);

        $this->assertDatabaseHas('hotspot_radius_events', [
            'hotspot_voucher_id' => $voucher->id,
            'event_type' => HotspotRadiusEventType::AuthAccept->value,
            'outcome' => 'accepted',
            'username' => $voucher->username,
        ]);
    }

    public function test_it_rejects_expired_voucher_and_returns_landing_page_redirect(): void
    {
        $voucher = $this->createVoucher(
            password: 'radius-pass',
            firstLoginAt: Carbon::parse('2026-04-15 08:00:00'),
            expiresAt: Carbon::parse('2026-04-22 08:59:00'),
            status: HotspotVoucherStatus::Active,
        );

        $response = $this->postJson('/api/v1/internal/hotspot-radius/authorize', [
            'username' => $voucher->username,
            'password' => 'radius-pass',
            'calling_station_id' => 'AA:BB:CC:DD:EE:FF',
            'nas_identifier' => 'AP-HOTSPOT-02',
            'requested_at' => '2026-04-22 09:00:00',
        ], $this->internalHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('data.accepted', false)
            ->assertJsonPath('data.reason', 'expired')
            ->assertJsonPath('data.redirect_url', 'https://landing.example/expired?voucher='.$voucher->username.'&reason=expired')
            ->assertJsonPath('data.radius_attributes.WISPr-Redirection-URL', 'https://landing.example/expired?voucher='.$voucher->username.'&reason=expired');

        $this->assertDatabaseHas('hotspot_radius_events', [
            'hotspot_voucher_id' => $voucher->id,
            'event_type' => HotspotRadiusEventType::AuthReject->value,
            'reason_code' => 'expired',
            'outcome' => 'rejected',
        ]);
    }

    public function test_it_tracks_accounting_sessions_from_start_until_stop(): void
    {
        $voucher = $this->createVoucher(
            password: 'radius-pass',
            firstLoginAt: Carbon::parse('2026-04-22 09:00:00'),
            expiresAt: Carbon::parse('2026-04-29 09:00:00'),
            lockedMac: 'AA:BB:CC:DD:EE:FF',
            status: HotspotVoucherStatus::Active,
        );

        $startResponse = $this->postJson('/api/v1/internal/hotspot-radius/accounting', [
            'username' => $voucher->username,
            'acct_status_type' => 'start',
            'acct_session_id' => 'S-001',
            'calling_station_id' => 'AA:BB:CC:DD:EE:FF',
            'nas_identifier' => 'AP-HOTSPOT-01',
            'input_octets' => 120,
            'output_octets' => 80,
            'event_at' => '2026-04-22 09:05:00',
        ], $this->internalHeaders());

        $startResponse
            ->assertOk()
            ->assertJsonPath('data.processed', true)
            ->assertJsonPath('data.action', 'created')
            ->assertJsonPath('data.session_status', 'open')
            ->assertJsonPath('data.bytes_used', 200);

        $stopResponse = $this->postJson('/api/v1/internal/hotspot-radius/accounting', [
            'username' => $voucher->username,
            'acct_status_type' => 'stop',
            'acct_session_id' => 'S-001',
            'calling_station_id' => 'AA:BB:CC:DD:EE:FF',
            'nas_identifier' => 'AP-HOTSPOT-01',
            'input_octets' => 400,
            'output_octets' => 300,
            'terminate_cause' => 'User-Request',
            'event_at' => '2026-04-22 09:15:00',
        ], $this->internalHeaders());

        $stopResponse
            ->assertOk()
            ->assertJsonPath('data.processed', true)
            ->assertJsonPath('data.action', 'updated')
            ->assertJsonPath('data.session_status', 'closed')
            ->assertJsonPath('data.bytes_used', 700)
            ->assertJsonPath('data.disconnect_required', false);

        $this->assertDatabaseHas('hotspot_voucher_sessions', [
            'hotspot_voucher_id' => $voucher->id,
            'acct_session_id' => 'S-001',
            'status' => 'closed',
            'total_octets' => 700,
            'terminate_cause' => 'User-Request',
        ]);

        $this->assertDatabaseHas('hotspot_radius_events', [
            'hotspot_voucher_id' => $voucher->id,
            'event_type' => HotspotRadiusEventType::AccountingStart->value,
            'acct_session_id' => 'S-001',
        ]);

        $this->assertDatabaseHas('hotspot_radius_events', [
            'hotspot_voucher_id' => $voucher->id,
            'event_type' => HotspotRadiusEventType::AccountingStop->value,
            'acct_session_id' => 'S-001',
        ]);
    }

    public function test_it_marks_voucher_for_disconnect_when_interim_update_depletes_data_limit(): void
    {
        $profile = $this->createProfile(
            validityMode: HotspotProfileValidityMode::Unlimited,
            validityDays: null,
            dataLimitBytes: 1000,
            expiredMode: HotspotExpiredMode::Data,
        );

        $voucher = $this->createVoucher(
            profile: $profile,
            password: 'radius-pass',
            firstLoginAt: Carbon::parse('2026-04-22 09:00:00'),
            expiresAt: null,
            lockedMac: 'AA:BB:CC:DD:EE:FF',
            status: HotspotVoucherStatus::Active,
        );

        $this->postJson('/api/v1/internal/hotspot-radius/accounting', [
            'username' => $voucher->username,
            'acct_status_type' => 'start',
            'acct_session_id' => 'S-QUOTA-1',
            'calling_station_id' => 'AA:BB:CC:DD:EE:FF',
            'nas_identifier' => 'AP-HOTSPOT-QUOTA',
            'input_octets' => 250,
            'output_octets' => 150,
            'event_at' => '2026-04-22 09:02:00',
        ], $this->internalHeaders())->assertOk();

        $response = $this->postJson('/api/v1/internal/hotspot-radius/accounting', [
            'username' => $voucher->username,
            'acct_status_type' => 'interim_update',
            'acct_session_id' => 'S-QUOTA-1',
            'calling_station_id' => 'AA:BB:CC:DD:EE:FF',
            'nas_identifier' => 'AP-HOTSPOT-QUOTA',
            'input_octets' => 700,
            'output_octets' => 400,
            'event_at' => '2026-04-22 09:10:00',
        ], $this->internalHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('data.processed', true)
            ->assertJsonPath('data.reason', 'depleted')
            ->assertJsonPath('data.voucher_status', HotspotVoucherStatus::Expired->value)
            ->assertJsonPath('data.bytes_used', 1100)
            ->assertJsonPath('data.disconnect_required', true)
            ->assertJsonPath('data.redirect_url', 'https://landing.example/expired?voucher='.$voucher->username.'&reason=depleted');

        $this->assertDatabaseHas('hotspot_vouchers', [
            'id' => $voucher->id,
            'bytes_used' => 1100,
            'status' => HotspotVoucherStatus::Expired->value,
        ]);

        $this->assertDatabaseHas('hotspot_radius_events', [
            'hotspot_voucher_id' => $voucher->id,
            'event_type' => HotspotRadiusEventType::AccountingInterimUpdate->value,
            'reason_code' => 'depleted',
        ]);
    }

    private function internalHeaders(): array
    {
        return [
            'X-Hotspot-Internal-Secret' => 'radius-secret',
        ];
    }

    private function createVoucher(
        ?HotspotProfile $profile = null,
        string $password = 'secret-123',
        ?Carbon $firstLoginAt = null,
        ?Carbon $expiresAt = null,
        ?string $lockedMac = null,
        int $bytesUsed = 0,
        HotspotVoucherStatus $status = HotspotVoucherStatus::Generated,
    ): HotspotVoucher {
        $profile ??= $this->createProfile();
        $reseller = $this->createReseller();
        $batch = VoucherBatch::query()->create([
            'reseller_id' => $reseller->id,
            'hotspot_profile_id' => $profile->id,
            'batch_code' => 'HB-'.uniqid(),
            'total_vouchers' => 1,
            'total_cost' => '10000.00',
            'generated_at' => now(),
            'created_by' => null,
        ]);

        return HotspotVoucher::query()->create([
            'batch_id' => $batch->id,
            'reseller_id' => $reseller->id,
            'hotspot_profile_id' => $profile->id,
            'username' => 'HS-'.uniqid(),
            'password' => $password,
            'voucher_code' => 'VC-'.uniqid(),
            'locked_mac' => $lockedMac,
            'first_login_at' => $firstLoginAt,
            'expires_at' => $expiresAt,
            'bytes_used' => $bytesUsed,
            'status' => $status->value,
        ]);
    }

    private function createProfile(
        HotspotProfileValidityMode $validityMode = HotspotProfileValidityMode::DaysAfterFirstLogin,
        ?int $validityDays = 7,
        ?int $dataLimitBytes = null,
        HotspotExpiredMode $expiredMode = HotspotExpiredMode::Time,
    ): HotspotProfile {
        return HotspotProfile::query()->create([
            'profile_name' => 'Profile '.uniqid(),
            'validity_mode' => $validityMode->value,
            'validity_days' => $validityDays,
            'data_limit_bytes' => $dataLimitBytes,
            'price' => '10000.00',
            'selling_price' => '15000.00',
            'user_lock' => HotspotProfileUserLock::Mac->value,
            'expired_mode' => $expiredMode->value,
            'is_active' => true,
        ]);
    }

    private function createReseller(): Reseller
    {
        return Reseller::query()->create([
            'reseller_code' => 'RSL-'.uniqid(),
            'full_name' => 'Reseller '.uniqid(),
            'phone' => '08123456789',
            'balance' => '100000.00',
            'status' => ResellerStatus::Active->value,
        ]);
    }
}
