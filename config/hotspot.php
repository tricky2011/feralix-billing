<?php

use App\Services\Hotspot\Radius\Providers\StubHotspotRadiusProvider;
use App\Services\Hotspot\Radius\FreeRadiusSqlProvider;

return [
    'radius' => [
        'provider' => env('HOTSPOT_RADIUS_PROVIDER', 'freeradius-sql'),
        'internal_secret' => env('HOTSPOT_RADIUS_INTERNAL_SECRET'),
        'expired_redirect_url' => env('HOTSPOT_RADIUS_EXPIRED_REDIRECT_URL'),
        'default_nas_secret' => env('HOTSPOT_DEFAULT_NAS_SECRET', 'secret'),
        'nas_default_ports' => env('HOTSPOT_NAS_DEFAULT_PORTS', 1812),

        'providers' => [
            'stub' => [
                'driver' => StubHotspotRadiusProvider::class,
            ],
            'freeradius-sql' => [
                'driver' => FreeRadiusSqlProvider::class,
            ],
        ],
    ],
];
