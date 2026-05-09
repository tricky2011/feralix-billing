<?php

namespace App\Services\Mikrotik;

use App\Contracts\Mikrotik\MikrotikApiClient;
use App\Models\Router;

class RouterOsVersionDetector
{
    /**
     * Detect RouterOS major version (6 or 7).
     * Uses cached value from router model if available.
     */
    public function detect(Router $router, MikrotikApiClient $client): int
    {
        // Use cached value if available
        if (!empty($router->ros_version)) {
            return (int) $router->ros_version;
        }

        try {
            $resource = $client->print('/system/resource', ['version']);
            $version  = $resource[0]['version'] ?? '6';
            $major    = (int) explode('.', $version)[0];
            $detected = $major >= 7 ? 7 : 6;

            // Cache to DB
            $router->updateQuietly(['ros_version' => (string) $detected]);

            return $detected;
        } catch (\Throwable) {
            return 6; // Safe fallback
        }
    }

    public function isV7(Router $router, MikrotikApiClient $client): bool
    {
        return $this->detect($router, $client) >= 7;
    }

    public function isV6(Router $router, MikrotikApiClient $client): bool
    {
        return $this->detect($router, $client) < 7;
    }
}
