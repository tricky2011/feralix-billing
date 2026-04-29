<?php

namespace App\Services\Mikrotik\Providers;

use App\Contracts\Mikrotik\MikrotikIpPoolProvider;
use App\Data\Mikrotik\MikrotikIpPoolRecord;
use App\Models\Router;

class FakeMikrotikIpPoolProvider implements MikrotikIpPoolProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function fetchIpPools(Router $router): array
    {
        $routerDatasets = array_replace(
            config('mikrotik.providers.fake.ip_pools', []),
            config('mikrotik.fake.ip_pools', []),
        );

        $dataset = $routerDatasets[$router->router_code]
            ?? $routerDatasets['router:'.$router->id]
            ?? $routerDatasets['default']
            ?? [];

        $records = array_map(
            static fn (array $payload): MikrotikIpPoolRecord => MikrotikIpPoolRecord::fromArray($payload),
            $dataset,
        );

        usort($records, static fn (MikrotikIpPoolRecord $left, MikrotikIpPoolRecord $right): int => $left->name <=> $right->name);

        return $records;
    }
}
