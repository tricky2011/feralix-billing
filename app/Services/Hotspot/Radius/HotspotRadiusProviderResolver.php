<?php

namespace App\Services\Hotspot\Radius;

use App\Contracts\Hotspot\HotspotRadiusProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class HotspotRadiusProviderResolver
{
    public function __construct(private readonly Container $container) {}

    public function resolve(?string $providerName = null): HotspotRadiusProvider
    {
        $resolvedName = trim((string) ($providerName ?: config('hotspot.radius.provider', 'stub')));
        $resolvedName = $resolvedName !== '' ? $resolvedName : 'stub';
        $driver = config("hotspot.radius.providers.{$resolvedName}.driver");

        if (! is_string($driver) || $driver === '') {
            throw new InvalidArgumentException(sprintf(
                'Unsupported hotspot RADIUS provider [%s]. Available providers: %s.',
                $resolvedName,
                implode(', ', array_keys(config('hotspot.radius.providers', []))),
            ));
        }

        $provider = $this->container->make($driver);

        if (! $provider instanceof HotspotRadiusProvider) {
            throw new InvalidArgumentException(sprintf(
                'Configured hotspot RADIUS provider [%s] must implement [%s].',
                $resolvedName,
                HotspotRadiusProvider::class,
            ));
        }

        return $provider;
    }
}
