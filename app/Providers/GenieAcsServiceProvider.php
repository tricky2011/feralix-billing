<?php

namespace App\Providers;

use App\Contracts\GenieAcs\GenieAcsClient;
use App\Contracts\GenieAcs\GenieAcsOntDataProvider;
use App\Services\GenieAcs\Clients\HttpGenieAcsClient;
use App\Services\GenieAcs\GenieAcsOntDataProviderResolver;
use Illuminate\Support\ServiceProvider;

class GenieAcsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GenieAcsClient::class, HttpGenieAcsClient::class);
        $this->app->singleton(GenieAcsOntDataProviderResolver::class);

        $this->app->bind(GenieAcsOntDataProvider::class, function ($app) {
            return $app->make(GenieAcsOntDataProviderResolver::class)->resolve();
        });
    }
}
