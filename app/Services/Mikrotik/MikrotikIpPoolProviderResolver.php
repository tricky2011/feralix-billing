<?php

namespace App\Services\Mikrotik;

use App\Contracts\Mikrotik\MikrotikIpPoolProvider;
use App\Services\Mikrotik\Providers\FakeMikrotikIpPoolProvider;
use App\Services\Mikrotik\Providers\RouterOsApiMikrotikIpPoolProvider;
use InvalidArgumentException;

class MikrotikIpPoolProviderResolver
{
    private ?MikrotikIpPoolProvider $cachedProvider = null;

    public function __construct(
        private readonly FakeMikrotikIpPoolProvider $fakeProvider,
        private readonly RouterOsApiMikrotikIpPoolProvider $routerOsApiProvider,
    ) {}

    public function resolve(?string $name = null): MikrotikIpPoolProvider
    {
        $resolvedName = $name ?? config('mikrotik.ip_pool_provider', 'routeros-api');

        return match ($resolvedName) {
            'fake' => $this->fakeProvider,
            'routeros-api', 'routeros_api' => $this->routerOsApiProvider,
            default => throw new InvalidArgumentException(
                sprintf('Unknown Mikrotik IP Pool provider "%s". Available: fake, routeros-api.', $resolvedName)
            ),
        };
    }
}
