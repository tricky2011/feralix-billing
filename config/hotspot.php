<?php

use App\Services\Hotspot\Radius\Providers\StubHotspotRadiusProvider;

return [
    'radius' => [
        'provider' => env('HOTSPOT_RADIUS_PROVIDER', 'stub'),
        'internal_secret' => env('HOTSPOT_RADIUS_INTERNAL_SECRET'),
        'expired_redirect_url' => env('HOTSPOT_RADIUS_EXPIRED_REDIRECT_URL'),

        'providers' => [
            'stub' => [
                'driver' => StubHotspotRadiusProvider::class,
            ],
        ],
    ],
];
