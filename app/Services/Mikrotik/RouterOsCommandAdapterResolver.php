<?php

namespace App\Services\Mikrotik;

use App\Contracts\Mikrotik\RouterOsCommandAdapter;
use App\Models\Router;
use App\Services\Mikrotik\Adapters\RouterOsV6CommandAdapter;
use App\Services\Mikrotik\Adapters\RouterOsV7CommandAdapter;

/**
 * Resolver untuk mendapatkan command adapter yang sesuai dengan versi ROS router.
 */
final class RouterOsCommandAdapterResolver
{
    private ?RouterOsV6CommandAdapter $v6 = null;

    private ?RouterOsV7CommandAdapter $v7 = null;

    public function resolve(Router $router): RouterOsCommandAdapter
    {
        $version = $router->ros_version ?? null;

        return $this->resolveByVersion($version);
    }

    public function resolveByVersion(?string $version): RouterOsCommandAdapter
    {
        if ($version === '7') {
            return $this->v7 ??= new RouterOsV7CommandAdapter();
        }

        return $this->v6 ??= new RouterOsV6CommandAdapter();
    }

    /**
     * Singleton instances untuk reuse.
     */
    public function v6(): RouterOsV6CommandAdapter
    {
        return $this->v6 ??= new RouterOsV6CommandAdapter();
    }

    public function v7(): RouterOsV7CommandAdapter
    {
        return $this->v7 ??= new RouterOsV7CommandAdapter();
    }
}
