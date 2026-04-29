<?php

namespace App\Services\Mikrotik;

use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Models\Router;
use Illuminate\Log\LogManager;

class RouterStatsService
{
    public function __construct(
        private readonly MikrotikApiClientFactory $clientFactory,
        private readonly LogManager $logManager,
    ) {}

    /**
     * @return array{success:bool, data:array<string,mixed>, message:string}
     */
    public function getRouterStats(Router $router): array
    {
        try {
            $client = $this->clientFactory->forRouter($router);

            $pppoeSecrets = $client->print('/ppp/secret', ['.id', 'name', 'profile', 'disabled', 'service']);
            $pppoeActive = $client->print('/ppp/active', ['.id', 'name', 'address', 'caller-id', 'session-timeout']);
            $dhcpLeases = $client->print('/ip/dhcp-server/lease', ['.id', 'address', 'status', 'server', 'mac-address', 'host-name']);
            $ipPools = $client->print('/ip/pool', ['.id', 'name', 'ranges', 'disabled']);
            $interfaces = $client->print('/interface', ['.id', 'name', 'type', 'disabled', 'running']);
            $vlanInterfaces = $client->print('/interface/vlan', ['.id', 'name', 'vlan-id', 'disabled']);
            $addresses = $client->print('/ip/address', ['.id', 'address', 'interface', 'disabled', 'dynamic']);

            $client->disconnect();

            $activeSecrets = collect($pppoeSecrets)
                ->filter(fn (array $s): bool => ! $this->isTruthy($s['disabled'] ?? null))
                ->count();

            $activePppoeUsers = collect($pppoeActive)
                ->filter(fn (array $a): bool => ! $this->isDisabled($a))
                ->count();

            $dhcpLeasesBound = collect($dhcpLeases)
                ->filter(fn (array $l): bool => in_array($l['status'] ?? '', ['bound', 'busy'], true) && ! $this->isTruthy($l['disabled'] ?? null))
                ->count();

            $totalDhcpLeases = count($dhcpLeases);

            $totalIpPoolAddresses = 0;
            foreach ($ipPools as $pool) {
                if ($this->isTruthy($pool['disabled'] ?? null)) {
                    continue;
                }

                $ranges = trim($pool['ranges'] ?? '');
                if ($ranges === '') {
                    continue;
                }

                $segments = array_filter(array_map('trim', explode(',', $ranges)));
                foreach ($segments as $segment) {
                    $parts = array_map('trim', explode('-', $segment, 2));
                    $start = $parts[0] ?? '';
                    $end = $parts[1] ?? $start;

                    $startLong = ip2long($start);
                    $endLong = ip2long($end);

                    if ($startLong !== false && $endLong !== false && $endLong >= $startLong) {
                        $totalIpPoolAddresses += ($endLong - $startLong + 1);
                    }
                }
            }

            $activeVlanInterfaces = collect($vlanInterfaces)
                ->filter(fn (array $v): bool => ! $this->isTruthy($v['disabled'] ?? null))
                ->count();

            $activeInterfaces = collect($interfaces)
                ->filter(fn (array $i): bool => ! $this->isTruthy($i['disabled'] ?? null) && $this->isTruthy($i['running'] ?? null))
                ->count();

            return [
                'success' => true,
                'message' => 'Router stats retrieved successfully.',
                'data' => [
                    'router_id' => $router->id,
                    'router_code' => $router->router_code,
                    'router_name' => $router->router_name,
                    'pppoe' => [
                        'total_secrets' => count($pppoeSecrets),
                        'active_secrets' => $activeSecrets,
                        'active_users' => $activePppoeUsers,
                    ],
                    'dhcp' => [
                        'total_leases' => $totalDhcpLeases,
                        'bound_leases' => $dhcpLeasesBound,
                    ],
                    'ip_pool' => [
                        'total_pools' => count(array_filter($ipPools, static fn (array $p): bool => ! $this->isTruthy($p['disabled'] ?? null))),
                        'total_addresses' => $totalIpPoolAddresses,
                    ],
                    'interfaces' => [
                        'total' => count($interfaces),
                        'active' => $activeInterfaces,
                        'vlan_count' => $activeVlanInterfaces,
                    ],
                    'addresses' => [
                        'total' => count($addresses),
                        'active' => count(array_filter($addresses, static fn (array $a): bool => ! $this->isTruthy($a['disabled'] ?? null) && ! $this->isTruthy($a['dynamic'] ?? null))),
                    ],
                ],
            ];
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to fetch router stats from MikroTik.', [
                'router_id' => $router->id,
                'router_code' => $router->router_code,
                'message' => $throwable->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal mengambil statistik router: '.$throwable->getMessage(),
                'data' => [
                    'router_id' => $router->id,
                    'router_code' => $router->router_code,
                    'router_name' => $router->router_name,
                ],
            ];
        }
    }

    private function isTruthy(?string $value): bool
    {
        return in_array(strtolower((string) ($value ?? '')), ['true', 'yes', 'on', '1'], true);
    }

    private function isDisabled(array $record): bool
    {
        return $this->isTruthy($record['disabled'] ?? null);
    }

    private function logger()
    {
        $channel = (string) (config('mikrotik.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }
}
