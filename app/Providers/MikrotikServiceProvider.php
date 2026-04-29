<?php

namespace App\Providers;

use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Contracts\Mikrotik\MikrotikIpPoolProvider;
use App\Contracts\Mikrotik\MikrotikVidInventoryProvider;
use App\Services\Mikrotik\Clients\SocketMikrotikApiClientFactory;
use App\Services\Mikrotik\IpPoolService;
use App\Services\Mikrotik\IpPoolVidAssignmentService;
use App\Services\Mikrotik\MikrotikIpPoolProviderResolver;
use App\Services\Mikrotik\MikrotikVidInventoryProviderResolver;
use App\Services\Mikrotik\RouterStatsService;
use Illuminate\Support\ServiceProvider;

class MikrotikServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MikrotikApiClientFactory::class, SocketMikrotikApiClientFactory::class);
        $this->app->singleton(MikrotikVidInventoryProviderResolver::class);
        $this->app->singleton(MikrotikIpPoolProviderResolver::class);

        $this->app->bind(MikrotikVidInventoryProvider::class, function ($app) {
            return $app->make(MikrotikVidInventoryProviderResolver::class)->resolve();
        });

        $this->app->bind(MikrotikIpPoolProvider::class, function ($app) {
            return $app->make(MikrotikIpPoolProviderResolver::class)->resolve();
        });

        $this->app->singleton(IpPoolService::class);
        $this->app->singleton(IpPoolVidAssignmentService::class);
        $this->app->singleton(RouterStatsService::class);
    }
}
