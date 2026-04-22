<?php

namespace App\Services\Mikrotik\Clients;

use App\Contracts\Mikrotik\MikrotikApiClient;
use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Data\Mikrotik\MikrotikApiConnectionConfig;
use App\Models\Router;
use Illuminate\Log\LogManager;

class SocketMikrotikApiClientFactory implements MikrotikApiClientFactory
{
    public function __construct(private readonly LogManager $logManager) {}

    public function forRouter(Router $router): MikrotikApiClient
    {
        $config = MikrotikApiConnectionConfig::fromRouter(
            $router,
            config('mikrotik.providers.routeros-api', []),
        );

        return new SocketMikrotikApiClient($config, $this->logManager);
    }
}
