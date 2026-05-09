<?php

namespace App\Services\Mikrotik\Providers;

use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Contracts\Mikrotik\MikrotikIpPoolProvider;
use App\Data\Mikrotik\MikrotikIpPoolRecord;
use App\Models\Router;
use Illuminate\Log\LogManager;
use Throwable;

class RouterOsApiMikrotikIpPoolProvider implements MikrotikIpPoolProvider
{
    public function __construct(
        private readonly MikrotikApiClientFactory $clientFactory,
        private readonly LogManager $logManager,
    ) {}

    public function name(): string
    {
        return 'routeros-api';
    }

    public function fetchIpPools(Router $router): array
    {
        $client = $this->clientFactory->forRouter($router);

        $this->logger()->info('Fetching IP pools from real Mikrotik router.', [
            'router_id' => $router->id,
            'router_code' => $router->router_code,
            'router_name' => $router->router_name,
            'mgmt_ip' => $router->mgmt_ip,
            'api_port' => $router->api_port,
        ]);

        try {
            $ipPools = $client->print('/ip/pool', ['.id', 'name', 'ranges']);
            $dhcpServers = $client->print('/ip/dhcp-server', ['.id', 'name', 'interface', 'address-pool', 'disabled', 'invalid']);
            $vlanInterfaces = $client->print('/interface/vlan', ['.id', 'name', 'vlan-id', 'disabled']);
            $dhcpLeases = $client->print('/ip/dhcp-server/lease', ['.id', 'address', 'status', 'server', 'active-server', 'disabled']);

            $records = $this->map($router, $ipPools, $dhcpServers, $vlanInterfaces, $dhcpLeases);
        } catch (Throwable $throwable) {
            $this->logger()->error('Failed to fetch IP pools from real Mikrotik router.', [
                'router_id' => $router->id,
                'router_code' => $router->router_code,
                'message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        } finally {
            $client->disconnect();
        }

        $this->logger()->info('Fetched IP pools from real Mikrotik router.', [
            'router_id' => $router->id,
            'router_code' => $router->router_code,
            'total_records' => count($records),
        ]);

        return $records;
    }

    /**
     * @param  list<array<string, string>>  $ipPools
     * @param  list<array<string, string>>  $dhcpServers
     * @param  list<array<string, string>>  $vlanInterfaces
     * @param  list<array<string, string>>  $dhcpLeases
     * @return list<MikrotikIpPoolRecord>
     */
    private function map(
        Router $router,
        array $ipPools,
        array $dhcpServers,
        array $vlanInterfaces,
        array $dhcpLeases,
    ): array {
        $vlanIndex = $this->buildVlanIndex($vlanInterfaces);
        $dhcpServerIndex = $this->buildDhcpServerIndex($dhcpServers);
        $usedAddresses = $this->buildUsedAddressesIndex($dhcpLeases, $dhcpServerIndex);

        $records = [];

        foreach ($ipPools as $pool) {
            if (! $this->isRecordUsable($pool)) {
                continue;
            }

            $name = $this->normalizeNullableString($pool['name'] ?? null);

            if ($name === null) {
                continue;
            }

            $ranges = $this->normalizeNullableString($pool['ranges'] ?? null) ?? '';

            $dhcpServer = $dhcpServerIndex[$name] ?? null;
            $vlanInfo = $dhcpServer !== null
                ? $vlanIndex[$dhcpServer['interface']] ?? null
                : null;

            $poolUsedAddresses = $usedAddresses[$name] ?? [];

            $records[] = MikrotikIpPoolRecord::fromMikrotikData(
                name: $name,
                ranges: $ranges,
                vlanId: $vlanInfo['vlan_id'] ?? null,
                vlanName: $vlanInfo['vlan_name'] ?? null,
                interface: $dhcpServer['interface'] ?? null,
                dhcpServerName: $dhcpServer['name'] ?? null,
                usedAddresses: $poolUsedAddresses,
            );
        }

        usort($records, static fn (MikrotikIpPoolRecord $left, MikrotikIpPoolRecord $right): int => $left->name <=> $right->name);

        return $records;
    }

    /**
     * @param  list<array<string, string>>  $vlanInterfaces
     * @return array<string, array{vlan_id:int|null, vlan_name:string|null}>
     */
    private function buildVlanIndex(array $vlanInterfaces): array
    {
        $index = [];

        foreach ($vlanInterfaces as $vlan) {
            if (! $this->isRecordUsable($vlan)) {
                continue;
            }

            $vlanName = $this->normalizeNullableString($vlan['name'] ?? null);

            if ($vlanName === null) {
                continue;
            }

            $vlanId = isset($vlan['vlan-id']) && is_numeric($vlan['vlan-id'])
                ? (int) $vlan['vlan-id']
                : null;

            $index[$vlanName] = [
                'vlan_id' => $vlanId,
                'vlan_name' => $vlanName,
            ];
        }

        return $index;
    }

    /**
     * @param  list<array<string, string>>  $dhcpServers
     * @return array<string, array{name:string|null, interface:string|null}>
     */
    private function buildDhcpServerIndex(array $dhcpServers): array
    {
        $index = [];

        foreach ($dhcpServers as $server) {
            if (! $this->isRecordUsable($server)) {
                continue;
            }

            $poolName = $this->normalizeNullableString($server['address-pool'] ?? null);

            if ($poolName === null) {
                continue;
            }

            $index[$poolName] = [
                'name' => $this->normalizeNullableString($server['name'] ?? null),
                'interface' => $this->normalizeNullableString($server['interface'] ?? null),
            ];
        }

        return $index;
    }

    /**
     * @param  list<array<string, string>>  $dhcpLeases
     * @param  array<string, array{name:string|null, interface:string|null}>  $dhcpServerIndex  pool_name → server info
     * @return array<string, list<string>>  pool_name → used addresses
     */
    private function buildUsedAddressesIndex(array $dhcpLeases, array $dhcpServerIndex = []): array
    {
        // Build reverse index: dhcp_server_name → pool_name
        // dhcpServerIndex key = pool_name, value = {name: server_name, ...}
        $serverNameToPoolName = [];
        foreach ($dhcpServerIndex as $poolName => $serverInfo) {
            $serverName = $serverInfo['name'] ?? null;
            if ($serverName !== null) {
                $serverNameToPoolName[$serverName] = $poolName;
            }
        }

        $index = [];

        foreach ($dhcpLeases as $lease) {
            if ($this->isTruthy($lease['disabled'] ?? null)) {
                continue;
            }

            $status = $this->normalizeNullableString($lease['status'] ?? null);
            $address = $this->normalizeNullableString($lease['address'] ?? null);

            if ($address === null) {
                continue;
            }

            $leaseServerName = $this->normalizeNullableString(
                $lease['server'] ?? $lease['active-server'] ?? null
            );

            if ($leaseServerName === null) {
                continue;
            }

            $isUsed = in_array($status, ['bound', 'busy'], true);

            if (! $isUsed) {
                continue;
            }

            // Resolve: server name → pool name
            // Prioritas 1: lookup via reverse index (ROS v7)
            // Prioritas 2: assume server name = pool name (ROS v6)
            $poolName = $serverNameToPoolName[$leaseServerName] ?? $leaseServerName;

            if (! isset($index[$poolName])) {
                $index[$poolName] = [];
            }

            $index[$poolName][] = $address;
        }

        return $index;
    }

    private function isRecordUsable(array $record): bool
    {
        if ($this->isTruthy($record['disabled'] ?? null)) {
            return false;
        }

        if ($this->isTruthy($record['invalid'] ?? null)) {
            return false;
        }

        return true;
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['true', 'yes', 'on', '1'], true);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function logger()
    {
        $channel = (string) (config('mikrotik.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }
}
