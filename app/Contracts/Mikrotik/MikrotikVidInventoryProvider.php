<?php

namespace App\Contracts\Mikrotik;

use App\Data\Mikrotik\MikrotikVidInventoryRecord;
use App\Models\Router;

interface MikrotikVidInventoryProvider
{
    public function name(): string;

    /**
     * @return list<MikrotikVidInventoryRecord>
     */
    public function fetchVidInventory(Router $router): array;
}
