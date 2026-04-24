<?php

namespace Tests\Feature\Api;

use App\Enums\HotspotExpiredMode;
use App\Enums\HotspotProfileUserLock;
use App\Enums\HotspotProfileValidityMode;
use App\Enums\HotspotVoucherStatus;
use App\Enums\ResellerStatus;
use App\Models\HotspotProfile;
use App\Models\HotspotVoucher;
use App\Models\Reseller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HotspotVoucherModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-22 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_generates_voucher_batch_and_deducts_reseller_balance(): void
    {
        $reseller = $this->createReseller(balance: 100000);
        $profile = $this->createProfile(price: 10000, sellingPrice: 15000);

        $response = $this->postJson('/api/v1/admin/voucher-batches', [
            'reseller_id' => $reseller->id,
            'hotspot_profile_id' => $profile->id,
            'total_vouchers' => 3,
            'voucher_code_length' => 8,
            'password_mode' => 'random_secure',
            'password_length' => 10,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.reseller_id', $reseller->id)
            ->assertJsonPath('data.hotspot_profile_id', $profile->id)
            ->assertJsonPath('data.total_vouchers', 3)
            ->assertJsonPath('data.total_cost', '30000.00')
            ->assertJsonCount(3, 'data.vouchers')
            ->assertJsonPath('data.vouchers.0.expires_at', null)
            ->assertJsonPath('data.vouchers.0.status', HotspotVoucherStatus::Generated->value)
            ->assertJsonPath('data.vouchers.0.has_password', true)
            ->assertJsonMissingPath('data.vouchers.0.password');

        $passwordMasked = $response->json('data.vouchers.0.password_masked');

        $this->assertIsString($passwordMasked);
        $this->assertSame(10, strlen($passwordMasked));
        $this->assertMatchesRegularExpression('/^\*+$/', $passwordMasked);

        $this->assertDatabaseHas('resellers', [
            'id' => $reseller->id,
            'balance' => '70000.00',
        ]);

        $this->assertDatabaseHas('voucher_batches', [
            'reseller_id' => $reseller->id,
            'hotspot_profile_id' => $profile->id,
            'total_vouchers' => 3,
            'total_cost' => '30000.00',
        ]);

        $this->assertDatabaseCount('hotspot_vouchers', 3);

        $voucher = HotspotVoucher::query()->firstOrFail();

        $this->assertNull($voucher->first_login_at);
        $this->assertNull($voucher->expires_at);
        $this->assertSame(HotspotVoucherStatus::Generated, $voucher->status);
        $this->assertSame(10, strlen((string) $voucher->password));
    }

    public function test_it_supports_prefix_random_username_and_same_as_username_password(): void
    {
        $reseller = $this->createReseller(balance: 50000);
        $profile = $this->createProfile(price: 10000, sellingPrice: 15000);

        $response = $this->postJson('/api/v1/admin/voucher-batches', [
            'reseller_id' => $reseller->id,
            'hotspot_profile_id' => $profile->id,
            'total_vouchers' => 1,
            'username_mode' => 'prefix_random',
            'username_prefix' => 'HS',
            'username_length' => 10,
            'password_mode' => 'same_as_username',
        ]);

        $response->assertCreated();

        $username = $response->json('data.vouchers.0.username');
        $passwordMasked = $response->json('data.vouchers.0.password_masked');

        $this->assertMatchesRegularExpression('/^HS[A-Z0-9]{8}$/', $username);
        $this->assertIsString($passwordMasked);
        $response->assertJsonMissingPath('data.vouchers.0.password');

        $voucher = HotspotVoucher::query()->firstOrFail();

        $this->assertSame($username, $voucher->password);
    }

    public function test_it_activates_voucher_on_first_login_locks_mac_and_sets_expiry(): void
    {
        $reseller = $this->createReseller(balance: 50000);
        $profile = $this->createProfile(price: 10000, sellingPrice: 15000, validityDays: 7);

        $batchResponse = $this->postJson('/api/v1/admin/voucher-batches', [
            'reseller_id' => $reseller->id,
            'hotspot_profile_id' => $profile->id,
            'total_vouchers' => 1,
        ]);

        $voucherId = $batchResponse->json('data.vouchers.0.id');
        $loginAt = '2026-04-22 10:30:00';

        $response = $this->postJson("/api/v1/admin/hotspot-vouchers/{$voucherId}/activate", [
            'mac_address' => 'AA-BB-CC-DD-EE-FF',
            'login_at' => $loginAt,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.locked_mac', 'AA:BB:CC:DD:EE:FF')
            ->assertJsonPath('data.first_login_at', Carbon::parse($loginAt)->toISOString())
            ->assertJsonPath('data.expires_at', Carbon::parse($loginAt)->addDays(7)->toISOString())
            ->assertJsonPath('data.status', HotspotVoucherStatus::Active->value);

        $this->assertDatabaseHas('hotspot_vouchers', [
            'id' => $voucherId,
            'locked_mac' => 'AA:BB:CC:DD:EE:FF',
            'status' => HotspotVoucherStatus::Active->value,
        ]);

        $secondResponse = $this->postJson("/api/v1/admin/hotspot-vouchers/{$voucherId}/activate", [
            'mac_address' => '11:22:33:44:55:66',
        ]);

        $secondResponse
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['mac_address']);
    }

    public function test_it_rejects_batch_generation_when_reseller_balance_is_insufficient(): void
    {
        $reseller = $this->createReseller(balance: 5000);
        $profile = $this->createProfile(price: 10000, sellingPrice: 15000);

        $response = $this->postJson('/api/v1/admin/voucher-batches', [
            'reseller_id' => $reseller->id,
            'hotspot_profile_id' => $profile->id,
            'total_vouchers' => 1,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['reseller_id']);

        $this->assertDatabaseCount('voucher_batches', 0);
        $this->assertDatabaseCount('hotspot_vouchers', 0);
        $this->assertDatabaseHas('resellers', [
            'id' => $reseller->id,
            'balance' => '5000.00',
        ]);
    }

    private function createProfile(
        float $price = 10000,
        float $sellingPrice = 15000,
        int $validityDays = 7,
    ): HotspotProfile {
        return HotspotProfile::query()->create([
            'profile_name' => 'Profile '.uniqid(),
            'validity_mode' => HotspotProfileValidityMode::DaysAfterFirstLogin->value,
            'validity_days' => $validityDays,
            'data_limit_bytes' => null,
            'price' => number_format($price, 2, '.', ''),
            'selling_price' => number_format($sellingPrice, 2, '.', ''),
            'user_lock' => HotspotProfileUserLock::Mac->value,
            'expired_mode' => HotspotExpiredMode::Time->value,
            'is_active' => true,
        ]);
    }

    private function createReseller(float $balance = 100000): Reseller
    {
        return Reseller::query()->create([
            'reseller_code' => 'RSL-'.uniqid(),
            'full_name' => 'Reseller '.uniqid(),
            'phone' => '08123456789',
            'balance' => number_format($balance, 2, '.', ''),
            'status' => ResellerStatus::Active->value,
        ]);
    }
}
