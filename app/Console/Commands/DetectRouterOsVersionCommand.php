<?php

namespace App\Console\Commands;

use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Models\Router;
use Illuminate\Console\Command;

class DetectRouterOsVersionCommand extends Command
{
    protected $signature = 'routers:detect-version {--router_id= : Specific router ID}';

    protected $description = 'Detect and store RouterOS version for all active routers';

    public function handle(MikrotikApiClientFactory $factory): int
    {
        $routers = Router::where('is_active', true)
            ->when($this->option('router_id'), fn ($q) => $q->where('id', (int) $this->option('router_id')))
            ->get();

        foreach ($routers as $router) {
            $this->info("Checking {$router->router_code} ({$router->router_name})...");
            try {
                $client = $factory->forRouter($router);
                $resource = $client->print('/system/resource', ['version']);
                $version = $resource[0]['version'] ?? 'unknown';
                $major = (int) explode('.', $version)[0];
                $rosV = $major >= 7 ? '7' : '6';

                $router->update(['ros_version' => $rosV]);
                $client->disconnect();

                $this->info("  ✓ ROS v{$rosV} (full: {$version})");
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}