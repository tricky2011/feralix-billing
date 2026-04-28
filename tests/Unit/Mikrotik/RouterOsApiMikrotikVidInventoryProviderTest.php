<?php

namespace Tests\Unit\Mikrotik;

use App\Contracts\Mikrotik\MikrotikApiClient;
use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikVidInventoryMapper;
use App\Services\Mikrotik\Providers\RouterOsApiMikrotikVidInventoryProvider;
use Illuminate\Log\LogManager;
use Tests\TestCase;

class RouterOsApiMikrotikVidInventoryProviderTest extends TestCase
{
    public function test_it_maps_real_routeros_inventory_into_internal_vid_records(): void
    {
        $router = new Router([
            'router_code' => 'RTR-REAL-1',
            'router_name' => 'Router Real 1',
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.10.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_active' => true,
        ]);
        $router->setAttribute('id', 10);

        $client = new FakeMikrotikApiClient([
            '/interface/vlan' => [
                ['name' => 'cust-110', 'vlan-id' => '110', 'disabled' => 'false'],
                ['name' => 'cust-120', 'vlan-id' => '120', 'disabled' => 'false'],
                ['name' => 'cust-999', 'vlan-id' => '999', 'disabled' => 'true'],
            ],
            '/ip/address' => [
                ['interface' => 'cust-110', 'address' => '10.10.110.1/29', 'network' => '10.10.110.0', 'disabled' => 'false', 'dynamic' => 'false', 'invalid' => 'false'],
                ['interface' => 'cust-120', 'address' => '10.10.120.1/29', 'network' => '10.10.120.0', 'disabled' => 'false', 'dynamic' => 'false', 'invalid' => 'false'],
            ],
            '/ip/pool' => [
                ['name' => 'pool-110', 'ranges' => '10.10.110.2-10.10.110.6'],
                ['name' => 'pool-120', 'ranges' => '10.10.120.2-10.10.120.4,10.10.120.10-10.10.120.12'],
            ],
            '/ip/dhcp-server' => [
                ['name' => 'dhcp-110', 'interface' => 'cust-110', 'address-pool' => 'pool-110', 'disabled' => 'false', 'invalid' => 'false'],
                ['name' => 'dhcp-120', 'interface' => 'cust-120', 'address-pool' => 'pool-120', 'disabled' => 'false', 'invalid' => 'false'],
            ],
            '/ip/dhcp-server/network' => [
                ['address' => '10.10.110.0/29', 'gateway' => '10.10.110.1', 'disabled' => 'false'],
                ['address' => '10.10.120.0/29', 'gateway' => '10.10.120.1', 'disabled' => 'false'],
            ],
        ]);

        $provider = new RouterOsApiMikrotikVidInventoryProvider(
            new FakeMikrotikApiClientFactory($client),
            $this->app->make(MikrotikVidInventoryMapper::class),
            $this->app->make(LogManager::class),
        );

        $records = $provider->fetchVidInventory($router);

        $this->assertCount(2, $records);
        $this->assertSame([110, 120], array_map(static fn ($record): int => $record->vid, $records));

        $this->assertSame('cust-110', $records[0]->vlanName);
        $this->assertSame('10.10.110.0/29', $records[0]->subnetCidr);
        $this->assertSame('10.10.110.1', $records[0]->gatewayIp);
        $this->assertSame('10.10.110.2', $records[0]->poolStartIp);
        $this->assertSame('10.10.110.6', $records[0]->poolEndIp);
        $this->assertSame(5, $records[0]->poolIpCount);

        $this->assertSame('cust-120', $records[1]->vlanName);
        $this->assertSame('10.10.120.0/29', $records[1]->subnetCidr);
        $this->assertSame('10.10.120.1', $records[1]->gatewayIp);
        $this->assertSame('10.10.120.2', $records[1]->poolStartIp);
        $this->assertSame('10.10.120.4', $records[1]->poolEndIp);
        $this->assertSame(6, $records[1]->poolIpCount);

        $this->assertSame([
            '/interface/vlan',
            '/ip/address',
            '/ip/pool',
            '/ip/dhcp-server',
            '/ip/dhcp-server/network',
        ], $client->requestedPaths);
        $this->assertTrue($client->disconnected);
    }

    public function test_it_maps_v_style_vlan_interfaces_to_network_cidr_not_host_ip(): void
    {
        // Two VLANs on the same router with different VID ranges — each must normalize
        // independently: address field (gateway) must NOT become subnet_cidr.
        $router = new Router([
            'router_code' => 'RTR-VNET-1',
            'router_name' => 'Router VNet 1',
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.10.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_active' => true,
        ]);
        $router->setAttribute('id', 20);

        $client = new FakeMikrotikApiClient([
            '/interface/vlan' => [
                ['name' => 'V-1001', 'vlan-id' => '1001', 'disabled' => 'false'],
                ['name' => 'V-2001', 'vlan-id' => '2001', 'disabled' => 'false'],
            ],
            '/ip/address' => [
                // Router A style: VID in 1xxx range
                ['interface' => 'V-1001', 'address' => '10.102.1.1/24', 'network' => '10.102.1.0', 'disabled' => 'false', 'dynamic' => 'false', 'invalid' => 'false'],
                // Router B style: VID in 2xxx range
                ['interface' => 'V-2001', 'address' => '10.201.1.1/24', 'network' => '10.201.1.0', 'disabled' => 'false', 'dynamic' => 'false', 'invalid' => 'false'],
            ],
            '/ip/pool' => [],
            '/ip/dhcp-server' => [],
            '/ip/dhcp-server/network' => [],
        ]);

        $provider = new RouterOsApiMikrotikVidInventoryProvider(
            new FakeMikrotikApiClientFactory($client),
            $this->app->make(MikrotikVidInventoryMapper::class),
            $this->app->make(LogManager::class),
        );

        $records = $provider->fetchVidInventory($router);

        $this->assertCount(2, $records);

        // V-1001: address 10.102.1.1/24 → gateway=10.102.1.1, subnet=10.102.1.0/24
        $this->assertSame(1001, $records[0]->vid);
        $this->assertSame('V-1001', $records[0]->vlanName);
        $this->assertSame('10.102.1.1', $records[0]->gatewayIp);
        $this->assertSame('10.102.1.0/24', $records[0]->subnetCidr);
        $this->assertNotSame('10.102.1.1/24', $records[0]->subnetCidr);

        // V-2001: address 10.201.1.1/24 → gateway=10.201.1.1, subnet=10.201.1.0/24
        $this->assertSame(2001, $records[1]->vid);
        $this->assertSame('V-2001', $records[1]->vlanName);
        $this->assertSame('10.201.1.1', $records[1]->gatewayIp);
        $this->assertSame('10.201.1.0/24', $records[1]->subnetCidr);
        $this->assertNotSame('10.201.1.1/24', $records[1]->subnetCidr);
    }

    public function test_it_calculates_network_cidr_when_network_field_absent_from_ip_address(): void
    {
        $router = new Router([
            'router_code' => 'RTR-VNET-2',
            'router_name' => 'Router VNet 2',
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.10.2',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_active' => true,
        ]);
        $router->setAttribute('id', 21);

        // /ip/address without 'network' field — mapper must calculate network from address
        $client = new FakeMikrotikApiClient([
            '/interface/vlan' => [
                ['name' => 'V-1002', 'vlan-id' => '1002', 'disabled' => 'false'],
            ],
            '/ip/address' => [
                ['interface' => 'V-1002', 'address' => '10.102.2.1/24', 'disabled' => 'false', 'dynamic' => 'false', 'invalid' => 'false'],
            ],
            '/ip/pool' => [],
            '/ip/dhcp-server' => [],
            '/ip/dhcp-server/network' => [],
        ]);

        $provider = new RouterOsApiMikrotikVidInventoryProvider(
            new FakeMikrotikApiClientFactory($client),
            $this->app->make(MikrotikVidInventoryMapper::class),
            $this->app->make(LogManager::class),
        );

        $records = $provider->fetchVidInventory($router);

        $this->assertCount(1, $records);
        $this->assertSame(1002, $records[0]->vid);
        $this->assertSame('10.102.2.1', $records[0]->gatewayIp);
        // Fallback calculation must still yield network CIDR, not host IP
        $this->assertSame('10.102.2.0/24', $records[0]->subnetCidr);
        $this->assertNotSame('10.102.2.1/24', $records[0]->subnetCidr);
    }
}

class FakeMikrotikApiClientFactory implements MikrotikApiClientFactory
{
    public function __construct(private readonly FakeMikrotikApiClient $client) {}

    public function forRouter(Router $router): MikrotikApiClient
    {
        return $this->client;
    }
}

class FakeMikrotikApiClient implements MikrotikApiClient
{
    public array $requestedPaths = [];

    public bool $disconnected = false;

    /**
     * @param  array<string, list<array<string, string>>>  $responses
     */
    public function __construct(private readonly array $responses) {}

    public function print(string $menuPath, array $properties = [], array $where = []): array
    {
        $normalizedPath = '/'.trim($menuPath, '/');
        $this->requestedPaths[] = $normalizedPath;

        return $this->responses[$normalizedPath] ?? [];
    }

    public function add(string $menuPath, array $attributes = []): void
    {
        // Not needed for this test.
    }

    public function remove(string $menuPath, string $id): void
    {
        // Not needed for this test.
    }

    public function disconnect(): void
    {
        $this->disconnected = true;
    }
}
