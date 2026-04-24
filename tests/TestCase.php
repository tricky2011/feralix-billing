<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->shouldAutoAuthenticateApi()) {
            $this->authenticateApi();
        }
    }

    protected function authenticateApi(?User $user = null, array $abilities = ['*']): User
    {
        $user ??= User::factory()->superadmin()->create();

        Sanctum::actingAs($user, $abilities);

        return $user;
    }

    private function shouldAutoAuthenticateApi(): bool
    {
        $skipApiAuthentication = property_exists($this, 'skipApiAuthentication')
            ? (bool) $this->skipApiAuthentication
            : false;

        return str_starts_with(static::class, 'Tests\\Feature\\Api\\')
            && ! $skipApiAuthentication;
    }
}
