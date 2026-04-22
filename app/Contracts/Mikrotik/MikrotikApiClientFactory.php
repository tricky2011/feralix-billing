<?php

namespace App\Contracts\Mikrotik;

use App\Models\Router;

interface MikrotikApiClientFactory
{
    public function forRouter(Router $router): MikrotikApiClient;
}
