<?php

namespace App\Services\Mikrotik;

use App\Data\Mikrotik\MikrotikVidInventoryRecord;
use App\Models\Router;
use Illuminate\Log\LogManager;
use Illuminate\Support\Collection;

class MikrotikVidInventoryMapper
{
    public function __construct(private readonly LogManager $logManager) {}

    /**
     * @param  list<array<string, string>>  $vlanInterfaces
     * @param  list<array<string, string>>  $ipAddresses
     * @param  list<array<string, string>>  $ipPools
     * @param  list<array<string, string>>  $dhcpServers
     * @param  list<array<string, string>>  $dhcpServerNetworks
     * @return list<MikrotikVidInventoryRecord>
     */
    public function map(
        Router $router,
        array $vlanInterfaces,
        array $ipAddresses,
        array $ipPools,
        array $dhcpServers,
        array $dhcpServerNetworks,
    ): array {
        $addressIndex = collect($ipAddresses)
            ->filter(fn (array $record): bool => $this->isRecordUsable($record, skipDynamic: true))
            ->groupBy(fn (array $record): string => $record['interface'] ?? '');

        $poolIndex = collect($ipPools)
            ->mapWithKeys(function (array $record) use ($router): array {
                $name = $this->normalizeNullableString($record['name'] ?? null);

                if ($name === null) {
                    return [];
                }

                return [$name => $this->parsePoolRanges($router, $name, $record['ranges'] ?? null)];
            });

        $dhcpServerIndex = collect($dhcpServers)
            ->filter(fn (array $record): bool => $this->isRecordUsable($record))
            ->groupBy(fn (array $record): string => $record['interface'] ?? '');

        $networkIndex = collect($dhcpServerNetworks)
            ->filter(fn (array $record): bool => $this->isRecordUsable($record))
            ->map(fn (array $record): ?array => $this->normalizeDhcpServerNetwork($record))
            ->filter()
            ->values();

        $records = [];

        foreach ($vlanInterfaces as $vlanInterface) {
            if (! $this->isRecordUsable($vlanInterface)) {
                continue;
            }

            $vid = (int) ($vlanInterface['vlan-id'] ?? 0);

            if ($vid < 1 || $vid > 4094) {
                continue;
            }

            $interfaceName = $this->normalizeNullableString($vlanInterface['name'] ?? null);

            if ($interfaceName === null) {
                continue;
            }

            $ipAddress = $this->normalizeIpAddressRecord($addressIndex->get($interfaceName, collect())->first());
            $dhcpServer = $dhcpServerIndex->get($interfaceName, collect())->first();
            $pool = $this->resolveDhcpPool($poolIndex, is_array($dhcpServer) ? $dhcpServer : null);
            $network = $this->matchDhcpServerNetwork($networkIndex, $ipAddress);

            $records[] = new MikrotikVidInventoryRecord(
                vid: $vid,
                vlanName: $interfaceName,
                subnetCidr: $network['subnet_cidr'] ?? $ipAddress['subnet_cidr'] ?? null,
                gatewayIp: $network['gateway_ip'] ?? $ipAddress['gateway_ip'] ?? null,
                poolStartIp: $pool['primary_start_ip'] ?? null,
                poolEndIp: $pool['primary_end_ip'] ?? null,
                poolIpCount: $pool['ip_count'] ?? null,
            );
        }

        usort($records, static fn (MikrotikVidInventoryRecord $left, MikrotikVidInventoryRecord $right): int => $left->vid <=> $right->vid);

        return $records;
    }

    private function resolveDhcpPool(Collection $poolIndex, ?array $dhcpServer): ?array
    {
        $poolName = $this->normalizeNullableString($dhcpServer['address-pool'] ?? null);

        if ($poolName === null) {
            return null;
        }

        $pool = $poolIndex->get($poolName);

        return is_array($pool) ? $pool : null;
    }

    private function normalizeIpAddressRecord(mixed $record): ?array
    {
        if (! is_array($record)) {
            return null;
        }

        $address = $this->normalizeNullableString($record['address'] ?? null);

        if ($address === null) {
            return null;
        }

        [$gatewayIp, $prefixLength] = $this->parseAddressWithPrefix($address);

        if ($gatewayIp === null || $prefixLength === null) {
            return null;
        }

        $networkAddress = $this->normalizeNullableString($record['network'] ?? null) ?? $this->calculateNetworkAddress($gatewayIp, $prefixLength);
        $subnetCidr = $networkAddress !== null ? sprintf('%s/%d', $networkAddress, $prefixLength) : null;

        return [
            'gateway_ip' => $gatewayIp,
            'subnet_cidr' => $subnetCidr,
        ];
    }

    private function normalizeDhcpServerNetwork(array $record): ?array
    {
        $subnetCidr = $this->normalizeNullableString($record['address'] ?? null);
        $gatewayIp = $this->normalizeNullableString($record['gateway'] ?? null);

        if ($subnetCidr === null && $gatewayIp === null) {
            return null;
        }

        return [
            'subnet_cidr' => $subnetCidr,
            'gateway_ip' => $gatewayIp,
        ];
    }

    private function matchDhcpServerNetwork(Collection $networks, ?array $ipAddress): ?array
    {
        if ($ipAddress === null) {
            return null;
        }

        $network = $networks->first(function (array $record) use ($ipAddress): bool {
            $sameGateway = ($record['gateway_ip'] ?? null) !== null
                && ($ipAddress['gateway_ip'] ?? null) !== null
                && $record['gateway_ip'] === $ipAddress['gateway_ip'];

            $sameSubnet = ($record['subnet_cidr'] ?? null) !== null
                && ($ipAddress['subnet_cidr'] ?? null) !== null
                && $record['subnet_cidr'] === $ipAddress['subnet_cidr'];

            return $sameGateway || $sameSubnet;
        });

        return is_array($network) ? $network : null;
    }

    private function parsePoolRanges(Router $router, string $poolName, mixed $ranges): ?array
    {
        $rangesString = $this->normalizeNullableString($ranges);

        if ($rangesString === null) {
            return null;
        }

        $segments = array_values(array_filter(array_map('trim', explode(',', $rangesString))));
        $parsedRanges = [];
        $ipCount = 0;

        foreach ($segments as $segment) {
            [$startIp, $endIp] = $this->parsePoolRangeSegment($segment);

            if ($startIp === null || $endIp === null) {
                continue;
            }

            $parsedRanges[] = [$startIp, $endIp];
            $ipCount += $this->ipRangeSize($startIp, $endIp);
        }

        if ($parsedRanges === []) {
            return null;
        }

        if (count($parsedRanges) > 1) {
            $this->logger()->warning('Mikrotik IP pool contains multiple ranges; sync keeps the first range as canonical.', [
                'router_id' => $router->id,
                'router_code' => $router->router_code,
                'pool_name' => $poolName,
                'ranges' => $segments,
            ]);
        }

        return [
            'primary_start_ip' => $parsedRanges[0][0],
            'primary_end_ip' => $parsedRanges[0][1],
            'ip_count' => $ipCount,
        ];
    }

    private function parsePoolRangeSegment(string $segment): array
    {
        $parts = array_map('trim', explode('-', $segment, 2));

        if ($parts === []) {
            return [null, null];
        }

        $startIp = $this->normalizeNullableString($parts[0] ?? null);
        $endIp = $this->normalizeNullableString($parts[1] ?? $parts[0] ?? null);

        if ($startIp === null || $endIp === null) {
            return [null, null];
        }

        if (! filter_var($startIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || ! filter_var($endIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return [null, null];
        }

        return [$startIp, $endIp];
    }

    private function parseAddressWithPrefix(string $value): array
    {
        $parts = explode('/', $value, 2);

        if (count($parts) !== 2) {
            return [null, null];
        }

        $ipAddress = $this->normalizeNullableString($parts[0] ?? null);
        $prefixLength = isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : null;

        if ($ipAddress === null || $prefixLength === null) {
            return [null, null];
        }

        return [$ipAddress, $prefixLength];
    }

    private function calculateNetworkAddress(string $ipAddress, int $prefixLength): ?string
    {
        if (! filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        $ipLong = ip2long($ipAddress);

        if ($ipLong === false || $prefixLength < 0 || $prefixLength > 32) {
            return null;
        }

        $mask = $prefixLength === 0 ? 0 : (-1 << (32 - $prefixLength));
        $network = long2ip($ipLong & $mask);

        return is_string($network) ? $network : null;
    }

    private function ipRangeSize(string $startIp, string $endIp): int
    {
        $start = ip2long($startIp);
        $end = ip2long($endIp);

        if ($start === false || $end === false || $end < $start) {
            return 0;
        }

        return (int) ($end - $start + 1);
    }

    private function isRecordUsable(array $record, bool $skipDynamic = false): bool
    {
        if ($this->isTruthy($record['disabled'] ?? null)) {
            return false;
        }

        if ($this->isTruthy($record['invalid'] ?? null)) {
            return false;
        }

        if ($skipDynamic && $this->isTruthy($record['dynamic'] ?? null)) {
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
