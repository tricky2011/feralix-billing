<?php

namespace Tests\Unit\Hotspot;

use App\Contracts\Hotspot\HotspotRadiusProvider;
use App\Services\Hotspot\Radius\Providers\StubHotspotRadiusProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotspotRadiusProviderResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_binds_the_default_stub_provider_from_configuration(): void
    {
        config()->set('hotspot.radius.provider', 'stub');

        $provider = $this->app->make(HotspotRadiusProvider::class);

        $this->assertInstanceOf(StubHotspotRadiusProvider::class, $provider);
    }
}
