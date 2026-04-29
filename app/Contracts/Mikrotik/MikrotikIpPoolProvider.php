<?php

namespace App\Contracts\Mikrotik;

use App\Data\Mikrotik\MikrotikIpPoolRecord;
use App\Models\Router;

interface MikrotikIpPoolProvider
{
    public function name(): string;

    /**
     * @return list<MikrotikIpPoolRecord>
     */
    public function fetchIpPools(Router $router): array;
}
