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
        $useRest = $router->use_rest_api
            ?? ($router->ros_version !== null && (int) $router->ros_version >= 7);

        $config = MikrotikApiConnectionConfig::fromRouter(
            $router,
            config('mikrotik.providers.routeros-api', []),
        );

        if ($useRest) {
            return new RestMikrotikApiClient(
                config:     $config,
                logManager: $this->logManager,
                restPort:   $router->rest_port ?? 80,
                tls:        $router->rest_tls ?? false,
            );
        }

        return new SocketMikrotikApiClient($config, $this->logManager);
    }
}