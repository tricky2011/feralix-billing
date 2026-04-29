<?php

use App\Services\Mikrotik\Providers\FakeMikrotikIpPoolProvider;
use App\Services\Mikrotik\Providers\FakeMikrotikVidInventoryProvider;
use App\Services\Mikrotik\Providers\RouterOsApiMikrotikIpPoolProvider;
use App\Services\Mikrotik\Providers\RouterOsApiMikrotikVidInventoryProvider;

return [
    'sync' => [
        'provider' => env('MIKROTIK_SYNC_PROVIDER', 'fake'),
    ],

    'ip_pool' => [
        'provider' => env('MIKROTIK_IP_POOL_PROVIDER', 'routeros-api'),
    ],

    'logging' => [
        'channel' => env('MIKROTIK_LOG_CHANNEL', env('AUTOMATION_LOG_CHANNEL', env('LOG_CHANNEL', 'stack'))),
    ],

    'isolation' => [
        'address_list_name' => env('MIKROTIK_ISOLATION_ADDRESS_LIST_NAME', 'ISOLIR_CUSTOMER'),
    ],

    'providers' => [
        'fake' => [
            'driver' => FakeMikrotikVidInventoryProvider::class,
            'ip_pool_driver' => FakeMikrotikIpPoolProvider::class,
            'routers' => [
                'default' => [
                    [
                        'vid' => 110,
                        'vlan_name' => 'cust-110',
                        'subnet_cidr' => '10.10.110.0/29',
                        'gateway_ip' => '10.10.110.1',
                        'pool_start_ip' => '10.10.110.2',
                        'pool_end_ip' => '10.10.110.6',
                    ],
                    [
                        'vid' => 120,
                        'vlan_name' => 'cust-120',
                        'subnet_cidr' => '10.10.120.0/29',
                        'gateway_ip' => '10.10.120.1',
                        'pool_start_ip' => '10.10.120.2',
                        'pool_end_ip' => '10.10.120.6',
                    ],
                    [
                        'vid' => 130,
                        'vlan_name' => 'cust-130-pending',
                        'subnet_cidr' => null,
                        'gateway_ip' => null,
                        'pool_start_ip' => null,
                        'pool_end_ip' => null,
                    ],
                ],
            ],
            'ip_pools' => [
                'default' => [
                    [
                        'name' => 'pool-cust-110',
                        'ranges' => ['start_ip' => '10.10.110.2', 'end_ip' => '10.10.110.6'],
                        'total_ips' => 5,
                        'used_ips' => 2,
                        'vlan_id' => 110,
                        'vlan_name' => 'cust-110',
                        'interface' => 'vlan110',
                        'dhcp_server_name' => 'dhcp-cust-110',
                        'used_addresses' => ['10.10.110.2', '10.10.110.3'],
                    ],
                    [
                        'name' => 'pool-cust-120',
                        'ranges' => ['start_ip' => '10.10.120.2', 'end_ip' => '10.10.120.6'],
                        'total_ips' => 5,
                        'used_ips' => 0,
                        'vlan_id' => 120,
                        'vlan_name' => 'cust-120',
                        'interface' => 'vlan120',
                        'dhcp_server_name' => 'dhcp-cust-120',
                        'used_addresses' => [],
                    ],
                    [
                        'name' => 'pool-cust-130',
                        'ranges' => ['start_ip' => '10.10.130.2', 'end_ip' => '10.10.130.6'],
                        'total_ips' => 5,
                        'used_ips' => 5,
                        'vlan_id' => 130,
                        'vlan_name' => 'cust-130',
                        'interface' => 'vlan130',
                        'dhcp_server_name' => 'dhcp-cust-130',
                        'used_addresses' => ['10.10.130.2', '10.10.130.3', '10.10.130.4', '10.10.130.5', '10.10.130.6'],
                    ],
                ],
            ],
        ],

        'routeros-api' => [
            'driver' => RouterOsApiMikrotikVidInventoryProvider::class,
            'ip_pool_driver' => RouterOsApiMikrotikIpPoolProvider::class,
            'timeouts' => [
                'connect' => (float) env('MIKROTIK_API_CONNECT_TIMEOUT', 5.0),
                'read' => (float) env('MIKROTIK_API_READ_TIMEOUT', 15.0),
            ],
            'ssl' => [
                'enabled' => env('MIKROTIK_API_SSL_ENABLED'),
                'verify_peer' => env('MIKROTIK_API_SSL_VERIFY_PEER', true),
                'verify_peer_name' => env('MIKROTIK_API_SSL_VERIFY_PEER_NAME', true),
                'allow_self_signed' => env('MIKROTIK_API_SSL_ALLOW_SELF_SIGNED', false),
                'cafile' => env('MIKROTIK_API_SSL_CAFILE'),
                'peer_name' => env('MIKROTIK_API_SSL_PEER_NAME'),
            ],
        ],
    ],

    // Backward-compatible alias while older tests/config references still use mikrotik.fake.*
    'fake' => [
        'routers' => [],
        'ip_pools' => [],
    ],
];
