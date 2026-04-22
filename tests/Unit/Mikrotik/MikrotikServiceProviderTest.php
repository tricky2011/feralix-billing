<?php

namespace Tests\Unit\Mikrotik;

use App\Contracts\Mikrotik\MikrotikVidInventoryProvider;
use App\Services\Mikrotik\MikrotikVidInventoryProviderResolver;
use App\Services\Mikrotik\Providers\FakeMikrotikVidInventoryProvider;
use App\Services\Mikrotik\Providers\RouterOsApiMikrotikVidInventoryProvider;
use Tests\TestCase;

class MikrotikServiceProviderTest extends TestCase
{
    public function test_it_binds_the_default_provider_from_configuration(): void
    {
        config()->set('mikrotik.sync.provider', 'fake');

        $provider = $this->app->make(MikrotikVidInventoryProvider::class);

        $this->assertInstanceOf(FakeMikrotikVidInventoryProvider::class, $provider);
    }

    public function test_it_can_resolve_the_real_provider_explicitly(): void
    {
        $provider = $this->app
            ->make(MikrotikVidInventoryProviderResolver::class)
            ->resolve('routeros-api');

        $this->assertInstanceOf(RouterOsApiMikrotikVidInventoryProvider::class, $provider);
    }
}
