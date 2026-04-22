<?php

namespace App\Services\Mikrotik;

use App\Contracts\Mikrotik\MikrotikVidInventoryProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class MikrotikVidInventoryProviderResolver
{
    public function __construct(private readonly Container $container) {}

    public function resolve(?string $providerName = null): MikrotikVidInventoryProvider
    {
        $resolvedName = trim((string) ($providerName ?: config('mikrotik.sync.provider', 'fake')));
        $resolvedName = $resolvedName !== '' ? $resolvedName : 'fake';

        $driver = config("mikrotik.providers.{$resolvedName}.driver");

        if (! is_string($driver) || $driver === '') {
            throw new InvalidArgumentException(sprintf(
                'Unsupported Mikrotik sync provider [%s]. Available providers: %s.',
                $resolvedName,
                implode(', ', array_keys(config('mikrotik.providers', []))),
            ));
        }

        $provider = $this->container->make($driver);

        if (! $provider instanceof MikrotikVidInventoryProvider) {
            throw new InvalidArgumentException(sprintf(
                'Configured Mikrotik provider [%s] must implement [%s].',
                $driver,
                MikrotikVidInventoryProvider::class,
            ));
        }

        return $provider;
    }
}
