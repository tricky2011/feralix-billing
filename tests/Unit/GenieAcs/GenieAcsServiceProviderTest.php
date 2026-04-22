<?php

namespace Tests\Unit\GenieAcs;

use App\Contracts\GenieAcs\GenieAcsOntDataProvider;
use App\Services\GenieAcs\GenieAcsOntDataProviderResolver;
use App\Services\GenieAcs\Providers\FakeGenieAcsOntDataProvider;
use App\Services\GenieAcs\Providers\NbiGenieAcsOntDataProvider;
use Tests\TestCase;

class GenieAcsServiceProviderTest extends TestCase
{
    public function test_it_binds_the_default_provider_from_configuration(): void
    {
        config()->set('genieacs.sync.provider', 'fake');

        $provider = $this->app->make(GenieAcsOntDataProvider::class);

        $this->assertInstanceOf(FakeGenieAcsOntDataProvider::class, $provider);
    }

    public function test_it_can_resolve_the_real_provider_explicitly(): void
    {
        $provider = $this->app
            ->make(GenieAcsOntDataProviderResolver::class)
            ->resolve('nbi');

        $this->assertInstanceOf(NbiGenieAcsOntDataProvider::class, $provider);
    }
}
