<?php

namespace App\Services\Mikrotik\Providers;

use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Contracts\Mikrotik\MikrotikVidInventoryProvider;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikVidInventoryMapper;
use Illuminate\Log\LogManager;
use Throwable;

class RouterOsApiMikrotikVidInventoryProvider implements MikrotikVidInventoryProvider
{
    public function __construct(
        private readonly MikrotikApiClientFactory $clientFactory,
        private readonly MikrotikVidInventoryMapper $mapper,
        private readonly LogManager $logManager,
    ) {}

    public function name(): string
    {
        return 'routeros-api';
    }

    public function fetchVidInventory(Router $router): array
    {
        $client = $this->clientFactory->forRouter($router);

        $this->logger()->info('Fetching VID inventory from real Mikrotik router.', [
            'router_id' => $router->id,
            'router_code' => $router->router_code,
            'router_name' => $router->router_name,
            'mgmt_ip' => $router->mgmt_ip,
            'api_port' => $router->api_port,
        ]);

        try {
            $records = $this->mapper->map(
                router: $router,
                vlanInterfaces: $client->print('/interface/vlan', ['.id', 'name', 'vlan-id', 'disabled']),
                ipAddresses: $client->print('/ip/address', ['.id', 'address', 'network', 'interface', 'disabled', 'dynamic', 'invalid']),
                ipPools: $client->print('/ip/pool', ['.id', 'name', 'ranges']),
                dhcpServers: $client->print('/ip/dhcp-server', ['.id', 'name', 'interface', 'address-pool', 'disabled', 'invalid']),
                dhcpServerNetworks: $client->print('/ip/dhcp-server/network', ['.id', 'address', 'gateway', 'disabled']),
            );
        } catch (Throwable $throwable) {
            $this->logger()->error('Failed to fetch VID inventory from real Mikrotik router.', [
                'router_id' => $router->id,
                'router_code' => $router->router_code,
                'message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        } finally {
            $client->disconnect();
        }

        $this->logger()->info('Fetched VID inventory from real Mikrotik router.', [
            'router_id' => $router->id,
            'router_code' => $router->router_code,
            'total_records' => count($records),
        ]);

        return $records;
    }

    private function logger()
    {
        $channel = (string) (config('mikrotik.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }
}
