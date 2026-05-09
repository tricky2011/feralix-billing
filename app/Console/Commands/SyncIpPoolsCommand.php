<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\Mikrotik\IpPoolSyncService;
use Illuminate\Console\Command;

class SyncIpPoolsCommand extends Command
{
    protected $signature   = 'ip-pools:sync {--router_id= : Sync specific router} {--force : Force even if fresh}';
    protected $description = 'Sync IP pools from Mikrotik to database cache';

    public function handle(IpPoolSyncService $syncService): int
    {
        $routerId = $this->option('router_id');
        $routers  = Router::where('is_active', true)
            ->when($routerId, fn ($q) => $q->where('id', (int) $routerId))
            ->get();

        if ($routers->isEmpty()) {
            $this->warn('No active routers found.');
            return self::SUCCESS;
        }

        foreach ($routers as $router) {
            $this->info("Syncing {$router->router_code}...");
            try {
                $count = $syncService->syncFromMikrotik($router);
                $this->info("  ✓ {$count} pools synced.");
            } catch (\Throwable $e) {
                $this->error("  ✗ {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}