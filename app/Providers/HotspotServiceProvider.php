<?php

namespace App\Providers;

use App\Contracts\Hotspot\HotspotRadiusProvider;
use App\Services\Hotspot\Radius\HotspotRadiusProviderResolver;
use Illuminate\Support\ServiceProvider;

class HotspotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HotspotRadiusProviderResolver::class);

        $this->app->bind(HotspotRadiusProvider::class, function ($app) {
            return $app->make(HotspotRadiusProviderResolver::class)->resolve();
        });
    }
}
