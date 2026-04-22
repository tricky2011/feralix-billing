<?php

namespace App\Providers;

use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Contracts\Mikrotik\MikrotikVidInventoryProvider;
use App\Services\Mikrotik\Clients\SocketMikrotikApiClientFactory;
use App\Services\Mikrotik\MikrotikVidInventoryProviderResolver;
use Illuminate\Support\ServiceProvider;

class MikrotikServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MikrotikApiClientFactory::class, SocketMikrotikApiClientFactory::class);
        $this->app->singleton(MikrotikVidInventoryProviderResolver::class);

        $this->app->bind(MikrotikVidInventoryProvider::class, function ($app) {
            return $app->make(MikrotikVidInventoryProviderResolver::class)->resolve();
        });
    }
}
