<?php

use App\Services\Hotspot\Radius\Providers\StubHotspotRadiusProvider;
use App\Services\Hotspot\Radius\FreeRadiusSqlProvider;

return [
    'radius' => [
        // The internal secret is sent by FreeRADIUS rlm_rest as the header
        // X-Hotspot-Internal-Secret when calling /api/v1/internal/hotspot-radius/authorize
        // and /accounting. Both values must match for the request to be accepted.
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