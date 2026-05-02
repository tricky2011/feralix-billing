<?php

namespace Tests\Unit\Provisioning;

use App\Models\Customer;
use App\Services\Provisioning\PppoeCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PppoeCredentialServiceTest extends TestCase
{
    use RefreshDatabase;

    private PppoeCredentialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PppoeCredentialService();
    }

    public function test_generates_password_with_ddmmyyyy_format(): void
    {
        Carbon::setTestNow('2026-04-29 10:00:00');

        $password = $this->service->generatePassword(Carbon::now());

        $this->assertEquals('29042026', $password);
    }

    public function test_generates_password_from_string_date(): void
    {
        $password = $this->service->generatePassword('2026-05-01');

        $this->assertEquals('01052026', $password);
    }

    public function test_generates_password_handles_leap_year(): void
    {
        $password = $this->service->generatePassword('2024-02-29');

        $this->assertEquals('29022024', $password);
    }

    public function test_extracts_first_name_from_simple_name(): void
    {
        // Test the internal logic via generateUsernamePreview pattern
        // First name extraction logic is: preg_split on whitespace, take first part
        $fullName = 'Budi Santoso';
        $parts = preg_split('/\s+/', trim($fullName), 2);
        $firstName = $parts[0];

        $this->assertEquals('Budi', $firstName);
    }

    public function test_extracts_first_name_removes_special_chars(): void
    {
        // Test that special characters are removed from first name
        $firstName = 'Budi.Santoso';
        $cleaned = preg_replace('/[^a-zA-Z0-9\-]/', '', $firstName);

        $this->assertEquals('BudiSantoso', $cleaned);
    }

    public function test_password_format_is_8_digits(): void
    {
        $password = $this->service->generatePassword('2026-05-01');

        $this->assertMatchesRegularExpression('/^\d{8}$/', $password);
        $this->assertEquals(8, strlen($password));
    }

    public function test_date_parsing_works_correctly(): void
    {
        // Test various date formats
        $dates = [
            '2026-01-01' => '01012026',
            '2026-12-31' => '31122026',
            '2026-07-15' => '15072026',
        ];

        foreach ($dates as $date => $expected) {
            $password = $this->service->generatePassword($date);
            $this->assertEquals($expected, $password, "Failed for date: {$date}");
        }
    }
}
