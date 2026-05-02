<?php

namespace App\Services\Mikrotik;

use App\Contracts\Mikrotik\MikrotikApiClient;
use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Models\Router;
use App\Models\Vid;
use Illuminate\Log\LogManager;

class MikrotikVlanService
{
    public function __construct(
        private readonly MikrotikApiClientFactory $clientFactory,
        private readonly LogManager $logManager,
    ) {}

    /**
     * Provision VLAN interface, IP pool, DHCP server, and queue on MikroTik
     *
     * @return array{success: bool, data: array, message: string}
     */
    public function provisionVlan(Router $router, Vid $vid, int $rateLimitMbps = 10): array
    {
        try {
            $client = $this->clientFactory->forRouter($router);
            $bridgeName = config('mikrotik.vlan.bridge_name', 'bridge-trunk');

            $result = [
                'vid_id' => $vid->id,
                'vid_number' => $vid->vid,
                'operations' => [],
            ];

            // 1. Create VLAN interface
            $vlanName = "vlan{$vid->vid}";
            $vlanResult = $this->ensureVlanInterface($client, $vlanName, (int) $vid->vid, $bridgeName);
            $result['operations']['vlan'] = $vlanResult;

            if (! ($vlanResult['success'] ?? false)) {
                $client->disconnect();
                return [
                    'success' => false,
                    'data' => $result,
                    'message' => 'Failed to create VLAN interface: ' . ($vlanResult['message'] ?? 'Unknown error'),
                ];
            }

            // 2. Create IP pool
            if ($vid->pool_start_ip && $vid->pool_end_ip) {
                $poolName = "pool-cust-{$vid->vid}";
                $poolResult = $this->ensureIpPool($client, $poolName, $vid->pool_start_ip, $vid->pool_end_ip);
                $result['operations']['pool'] = $poolResult;

                if (! ($poolResult['success'] ?? false)) {
                    $this->logger()->warning('Failed to create IP pool, continuing with VLAN.', [
                        'vid_id' => $vid->id,
                        'message' => $poolResult['message'] ?? 'Unknown error',
                    ]);
                }
            }

            // 3. Create DHCP server
            if ($vid->pool_start_ip && $vid->pool_end_ip && $vid->gateway_ip) {
                $dhcpName = "dhcp-cust-{$vid->vid}";
                $poolName = "pool-cust-{$vid->vid}";
                $dhcpResult = $this->ensureDhcpServer(
                    $client,
                    $dhcpName,
                    $vlanName,
                    $poolName,
                    $vid->gateway_ip,
                );
                $result['operations']['dhcp'] = $dhcpResult;

                if (! ($dhcpResult['success'] ?? false)) {
                    $this->logger()->warning('Failed to create DHCP server, continuing.', [
                        'vid_id' => $vid->id,
                        'message' => $dhcpResult['message'] ?? 'Unknown error',
                    ]);
                }
            }

            // 4. Create simple queue
            $queueResult = $this->ensureSimpleQueue($client, $vid, $rateLimitMbps);
            $result['operations']['queue'] = $queueResult;

            $this->logger()->info('VLAN provisioned successfully on MikroTik.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'vid_id' => $vid->id,
                'vid_number' => $vid->vid,
            ]);

            $client->disconnect();

            return [
                'success' => true,
                'data' => $result,
                'message' => 'VLAN provisioned successfully.',
            ];
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to provision VLAN on MikroTik.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'vid_id' => $vid->id,
                'message' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            if (isset($client)) {
                $client->disconnect();
            }

            return [
                'success' => false,
                'data' => [],
                'message' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Deprovision (remove) VLAN interface, IP pool, DHCP server, and queue from MikroTik
     *
     * @return array{success: bool, data: array, message: string}
     */
    public function deprovisionVlan(Router $router, Vid $vid): array
    {
        try {
            $client = $this->clientFactory->forRouter($router);
            $vlanName = "vlan{$vid->vid}";
            $poolName = "pool-cust-{$vid->vid}";
            $dhcpName = "dhcp-cust-{$vid->vid}";
            $queueName = "queue-{$vid->vid}";

            $operations = [];

            // 1. Remove DHCP server first (references pool)
            $dhcp = $client->print('/ip/dhcp-server', ['.id'], ['name' => $dhcpName]);
            if (! empty($dhcp)) {
                $client->remove('/ip/dhcp-server', $dhcp[0]['.id']);
                $operations['dhcp'] = 'removed';
                $this->logger()->debug('DHCP server removed.', ['vid' => $vid->vid]);
            }

            // 2. Remove IP pool
            $pool = $client->print('/ip/pool', ['.id'], ['name' => $poolName]);
            if (! empty($pool)) {
                $client->remove('/ip/pool', $pool[0]['.id']);
                $operations['pool'] = 'removed';
                $this->logger()->debug('IP pool removed.', ['vid' => $vid->vid]);
            }

            // 3. Remove queue
            $queue = $client->print('/queue/simple', ['.id'], ['name' => $queueName]);
            if (! empty($queue)) {
                $client->remove('/queue/simple', $queue[0]['.id']);
                $operations['queue'] = 'removed';
                $this->logger()->debug('Queue removed.', ['vid' => $vid->vid]);
            }

            // 4. Remove VLAN interface (remove last as it's on bridge)
            $vlan = $client->print('/interface/vlan', ['.id'], ['name' => $vlanName]);
            if (! empty($vlan)) {
                $client->remove('/interface/vlan', $vlan[0]['.id']);
                $operations['vlan'] = 'removed';
                $this->logger()->debug('VLAN interface removed.', ['vid' => $vid->vid]);
            }

            $this->logger()->info('VLAN deprovisioned from MikroTik.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'vid_id' => $vid->id,
                'vid_number' => $vid->vid,
            ]);

            $client->disconnect();

            return [
                'success' => true,
                'data' => ['operations' => $operations],
                'message' => 'VLAN deprovisioned successfully.',
            ];
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to deprovision VLAN.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'vid_id' => $vid->id,
                'message' => $throwable->getMessage(),
            ]);

            if (isset($client)) {
                $client->disconnect();
            }

            return [
                'success' => false,
                'data' => [],
                'message' => $throwable->getMessage(),
            ];
        }
    }

    private function ensureVlanInterface(MikrotikApiClient $client, string $name, int $vlanId, string $interface): array
    {
        try {
            $existing = $client->print('/interface/vlan', ['.id', 'name'], ['name' => $name]);

            if (! empty($existing)) {
                return [
                    'success' => true,
                    'action' => 'skipped',
                    'id' => $existing[0]['.id'],
                    'message' => 'VLAN interface already exists.',
                ];
            }

            $client->add('/interface/vlan', [
                'name' => $name,
                'vlan-id' => (string) $vlanId,
                'interface' => $interface,
            ]);

            $created = $client->print('/interface/vlan', ['.id'], ['name' => $name]);

            return [
                'success' => true,
                'action' => 'created',
                'id' => $created[0]['.id'] ?? null,
                'message' => 'VLAN interface created.',
            ];
        } catch (\Throwable $throwable) {
            return [
                'success' => false,
                'action' => 'error',
                'message' => $throwable->getMessage(),
            ];
        }
    }

    private function ensureIpPool(MikrotikApiClient $client, string $name, string $startIp, string $endIp): array
    {
        try {
            $existing = $client->print('/ip/pool', ['.id'], ['name' => $name]);

            if (! empty($existing)) {
                return [
                    'success' => true,
                    'action' => 'skipped',
                    'id' => $existing[0]['.id'],
                    'message' => 'IP pool already exists.',
                ];
            }

            $client->add('/ip/pool', [
                'name' => $name,
                'ranges' => "{$startIp}-{$endIp}",
            ]);

            $created = $client->print('/ip/pool', ['.id'], ['name' => $name]);

            return [
                'success' => true,
                'action' => 'created',
                'id' => $created[0]['.id'] ?? null,
                'message' => 'IP pool created.',
            ];
        } catch (\Throwable $throwable) {
            return [
                'success' => false,
                'action' => 'error',
                'message' => $throwable->getMessage(),
            ];
        }
    }

    private function ensureDhcpServer(
        MikrotikApiClient $client,
        string $name,
        string $interface,
        string $pool,
        string $gateway,
    ): array {
        try {
            $existing = $client->print('/ip/dhcp-server', ['.id'], ['name' => $name]);

            if (! empty($existing)) {
                return [
                    'success' => true,
                    'action' => 'skipped',
                    'id' => $existing[0]['.id'],
                    'message' => 'DHCP server already exists.',
                ];
            }

            // First, ensure DHCP network exists
            $networkAddress = $this->calculateNetworkAddress($gateway);
            if ($networkAddress) {
                try {
                    $client->add('/ip/dhcp-server/network', [
                        'address' => $networkAddress,
                        'gateway' => $gateway,
                    ]);
                } catch (\Throwable $e) {
                    // Network might already exist, continue
                    $this->logger()->debug('DHCP network might already exist.', ['message' => $e->getMessage()]);
                }
            }

            $client->add('/ip/dhcp-server', [
                'name' => $name,
                'interface' => $interface,
                'address-pool' => $pool,
                'disabled' => 'no',
            ]);

            $created = $client->print('/ip/dhcp-server', ['.id'], ['name' => $name]);

            return [
                'success' => true,
                'action' => 'created',
                'id' => $created[0]['.id'] ?? null,
                'message' => 'DHCP server created.',
            ];
        } catch (\Throwable $throwable) {
            return [
                'success' => false,
                'action' => 'error',
                'message' => $throwable->getMessage(),
            ];
        }
    }

    private function ensureSimpleQueue(MikrotikApiClient $client, Vid $vid, int $rateLimitMbps): array
    {
        try {
            $queueName = "queue-{$vid->vid}";
            $subnet = $vid->subnet_cidr ?? $this->calculateNetworkCidr($vid->vid);
            $targetRate = "{$rateLimitMbps}M/{$rateLimitMbps}M";

            $existing = $client->print('/queue/simple', ['.id'], ['name' => $queueName]);

            if (! empty($existing)) {
                return [
                    'success' => true,
                    'action' => 'skipped',
                    'id' => $existing[0]['.id'],
                    'message' => 'Queue already exists.',
                ];
            }

            $client->add('/queue/simple', [
                'name' => $queueName,
                'target' => $subnet,
                'max-limit' => $targetRate,
                'comment' => "VID-{$vid->vid} rate limit",
            ]);

            $created = $client->print('/queue/simple', ['.id'], ['name' => $queueName]);

            return [
                'success' => true,
                'action' => 'created',
                'id' => $created[0]['.id'] ?? null,
                'message' => 'Queue created.',
            ];
        } catch (\Throwable $throwable) {
            return [
                'success' => false,
                'action' => 'error',
                'message' => $throwable->getMessage(),
            ];
        }
    }

    private function calculateNetworkAddress(string $gatewayIp): ?string
    {
        if (! filter_var($gatewayIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        $parts = explode('.', $gatewayIp);
        if (count($parts) !== 4) {
            return null;
        }

        // Assume /24 network, so network is x.x.x.0/24
        return "{$parts[0]}.{$parts[1]}.{$parts[2]}.0/24";
    }

    private function calculateNetworkCidr(int $vidNumber): string
    {
        // Calculate network from VID number
        // Format: 10.{vid_prefix}.{vid_suffix}.0/24
        $prefix = (int) floor($vidNumber / 256);
        $suffix = $vidNumber % 256;

        return "10.{$prefix}.{$suffix}.0/24";
    }

    private function logger()
    {
        $channel = (string) (config('mikrotik.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }
}
