<?php

namespace App\Services\GenieAcs;

use App\Contracts\GenieAcs\GenieAcsOntDataProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class GenieAcsOntDataProviderResolver
{
    public function __construct(private readonly Container $container) {}

    public function resolve(?string $providerName = null): GenieAcsOntDataProvider
    {
        $resolvedName = trim((string) ($providerName ?: config('genieacs.sync.provider', 'fake')));
        $resolvedName = $resolvedName !== '' ? $resolvedName : 'fake';

        $driver = config("genieacs.providers.{$resolvedName}.driver");

        if (! is_string($driver) || $driver === '') {
            throw new InvalidArgumentException(sprintf(
                'Unsupported GenieACS sync provider [%s]. Available providers: %s.',
                $resolvedName,
                implode(', ', array_keys(config('genieacs.providers', []))),
            ));
        }

        $provider = $this->container->make($driver);

        if (! $provider instanceof GenieAcsOntDataProvider) {
            throw new InvalidArgumentException(sprintf(
                'Configured GenieACS provider [%s] must implement [%s].',
                $driver,
                GenieAcsOntDataProvider::class,
            ));
        }

        return $provider;
    }
}
