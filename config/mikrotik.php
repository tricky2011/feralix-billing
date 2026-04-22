<?php

use App\Services\Mikrotik\Providers\FakeMikrotikVidInventoryProvider;
use App\Services\Mikrotik\Providers\RouterOsApiMikrotikVidInventoryProvider;

return [
    'sync' => [
        'provider' => env('MIKROTIK_SYNC_PROVIDER', 'fake'),
    ],

    'logging' => [
        'channel' => env('MIKROTIK_LOG_CHANNEL', env('AUTOMATION_LOG_CHANNEL', env('LOG_CHANNEL', 'stack'))),
    ],

    'providers' => [
        'fake' => [
            'driver' => FakeMikrotikVidInventoryProvider::class,
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
        ],

        'routeros-api' => [
            'driver' => RouterOsApiMikrotikVidInventoryProvider::class,
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
    ],
];
