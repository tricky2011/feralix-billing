<?php

use App\Services\GenieAcs\Providers\FakeGenieAcsOntDataProvider;
use App\Services\GenieAcs\Providers\NbiGenieAcsOntDataProvider;

return [
    'sync' => [
        'provider' => env('GENIEACS_SYNC_PROVIDER', 'fake'),
        'online_threshold_minutes' => (int) env('GENIEACS_ONLINE_THRESHOLD_MINUTES', 10),
    ],

    'base_url' => env('GENIEACS_BASE_URL', 'http://127.0.0.1:7557'),

    'auth' => [
        'token' => env('GENIEACS_TOKEN'),
        'username' => env('GENIEACS_USERNAME'),
        'password' => env('GENIEACS_PASSWORD'),
    ],

    'http' => [
        'timeout' => (float) env('GENIEACS_HTTP_TIMEOUT', 15),
        'connect_timeout' => (float) env('GENIEACS_HTTP_CONNECT_TIMEOUT', 5),
        'verify_ssl' => env('GENIEACS_HTTP_VERIFY_SSL', true),
        'headers' => [
            'X-Requested-With' => 'feralix-billing',
        ],
    ],

    'logging' => [
        'channel' => env('GENIEACS_LOG_CHANNEL', env('AUTOMATION_LOG_CHANNEL', env('LOG_CHANNEL', 'stack'))),
    ],

    'identifiers' => [
        'serial_query_paths' => [
            'DeviceID.SerialNumber',
            'Device.DeviceInfo.SerialNumber',
            'InternetGatewayDevice.DeviceInfo.SerialNumber',
        ],
    ],

    'fields' => [
        'last_inform' => [
            '_lastInform',
        ],
        'online' => [
            'VirtualParameters.Online',
            'VirtualParameters.DeviceOnline',
            'VirtualParameters.PonOnline',
        ],
        'serial_number' => [
            'DeviceID.SerialNumber',
            'Device.DeviceInfo.SerialNumber',
            'InternetGatewayDevice.DeviceInfo.SerialNumber',
        ],
        'optical_status' => [
            'VirtualParameters.OpticalStatus',
            'VirtualParameters.PonStatus',
        ],
        'optical_info' => [
            'VirtualParameters.OpticalInfo',
            'VirtualParameters.PonInfo',
        ],
        'rx_power' => [
            'VirtualParameters.OpticalRxPower',
            'VirtualParameters.RxPower',
            'VirtualParameters.PonRxPower',
            'Device.Optical.Interface.1.RXPower',
            'InternetGatewayDevice.X_ZTE-COM_WANPONInterfaceConfig.RXPower',
        ],
        'tx_power' => [
            'VirtualParameters.OpticalTxPower',
            'VirtualParameters.TxPower',
            'VirtualParameters.PonTxPower',
            'Device.Optical.Interface.1.TXPower',
            'InternetGatewayDevice.X_ZTE-COM_WANPONInterfaceConfig.TXPower',
        ],
        'ssid_name' => [
            'VirtualParameters.WifiSSID',
            'VirtualParameters.SSID',
            'Device.LANDevice.1.WLANConfiguration.1.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
        ],
        'wifi_password' => [
            'VirtualParameters.WifiPassword',
            'VirtualParameters.WifiKey',
            'Device.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase',
            'Device.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey',
        ],
    ],

    'providers' => [
        'fake' => [
            'driver' => FakeGenieAcsOntDataProvider::class,
            'onts' => [
                'default' => [
                    'device_id' => 'FAKE-ONT-001',
                    'status' => 'online',
                    'optical_status' => 'normal',
                    'optical_info' => 'RX -22.40 dBm | TX 2.15 dBm',
                    'rx_power' => -22.40,
                    'tx_power' => 2.15,
                    'ssid_name' => 'Feralix-Lab',
                    'wifi_password' => 'feralix123',
                    'last_inform_at' => '2026-04-22T00:00:00Z',
                ],
            ],
        ],

        'nbi' => [
            'driver' => NbiGenieAcsOntDataProvider::class,
        ],
    ],

    // Backward-compatible alias while older tests/config references still use genieacs.fake.*
    'fake' => [
        'onts' => [],
    ],
];
