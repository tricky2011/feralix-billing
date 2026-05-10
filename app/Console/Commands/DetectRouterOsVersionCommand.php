<?php

namespace App\Console\Commands;

use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Data\Mikrotik\MikrotikApiConnectionConfig;
use App\Models\Router;
use App\Services\Mikrotik\Clients\RestMikrotikApiClient;
use App\Services\Mikrotik\Clients\SocketMikrotikApiClient;
use Illuminate\Console\Command;
use Illuminate\Log\LogManager;

class DetectRouterOsVersionCommand extends Command
{
    protected $signature   = 'routers:detect-version {--router_id= : Specific router ID} {--force : Re-detect even if already set}';
    protected $description = 'Detect RouterOS version and auto-configure REST API settings';

    public function handle(MikrotikApiClientFactory $factory): int
    {
        $routerId = $this->option('router_id');
        $force    = (bool) $this->option('force');

        $routers = Router::where('is_active', true)
            ->when($routerId, fn ($q) => $q->where('id', (int) $routerId))
            ->when(!$force, fn ($q) => $q->whereNull('ros_version'))
            ->get();

        if ($routers->isEmpty()) {
            $this->info('All routers already have version detected. Use --force to re-detect.');
            return self::SUCCESS;
        }

        $logManager = app(LogManager::class);

        foreach ($routers as $router) {
            $this->info("Checking {$router->router_code} ({$router->router_name})...");

            try {
                // Always try Socket API first to detect version
                $config  = MikrotikApiConnectionConfig::fromRouter($router, []);
                $socket  = new SocketMikrotikApiClient($config, $logManager);

                $resource = $socket->print('/system/resource', ['version', 'board-name', 'platform']);
                $version  = $resource[0]['version'] ?? 'unknown';
                $board    = $resource[0]['board-name'] ?? 'unknown';
                $major    = (int) explode('.', $version)[0];
                $rosV     = $major >= 7 ? '7' : '6';

                $socket->disconnect();

                $updateData = [
                    'ros_version'  => $rosV,
                    'use_rest_api' => $major >= 7,
                ];

                // For v7, try to detect REST API availability
                if ($major >= 7) {
                    $restPort = $router->rest_port ?? 80;
                    $restTls  = $router->rest_tls ?? false;

                    try {
                        $restClient = new RestMikrotikApiClient($config, $logManager, $restPort, $restTls);
                        $restResource = $restClient->print('/system/resource', ['version']);
                        $restClient->disconnect();

                        if (! empty($restResource)) {
                            $this->info("  ✓ REST API working on port {$restPort}");
                            $updateData['use_rest_api'] = true;
                        }
                    } catch (\Throwable $e) {
                        $this->warn("  ⚠ REST API not available on port {$restPort} ({$e->getMessage()}), using Socket API");
                        $updateData['use_rest_api'] = false;
                    }
                }

                $router->update($updateData);

                $this->info("  ✓ ROS v{$rosV} | Board: {$board} | Version: {$version} | REST: " . ($updateData['use_rest_api'] ? 'YES' : 'NO'));

            } catch (\Throwable $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}