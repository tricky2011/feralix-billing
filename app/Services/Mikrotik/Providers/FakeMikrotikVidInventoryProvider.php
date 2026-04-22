<?php

namespace App\Services\Mikrotik\Providers;

use App\Contracts\Mikrotik\MikrotikVidInventoryProvider;
use App\Data\Mikrotik\MikrotikVidInventoryRecord;
use App\Models\Router;

class FakeMikrotikVidInventoryProvider implements MikrotikVidInventoryProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function fetchVidInventory(Router $router): array
    {
        $routerDatasets = config('mikrotik.providers.fake.routers', config('mikrotik.fake.routers', []));
        $dataset = $routerDatasets[$router->router_code]
            ?? $routerDatasets['router:'.$router->id]
            ?? $routerDatasets['default']
            ?? [];

        $records = array_map(
            static fn (array $payload): MikrotikVidInventoryRecord => MikrotikVidInventoryRecord::fromArray($payload),
            $dataset
        );

        usort($records, static fn (MikrotikVidInventoryRecord $left, MikrotikVidInventoryRecord $right): int => $left->vid <=> $right->vid);

        return $records;
    }
}
