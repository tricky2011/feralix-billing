<?php

namespace App\Jobs;

use App\Models\Router;
use App\Services\Monitoring\PppoeMonitorSyncService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncPppoeMonitorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithAutomationLogging, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        private readonly int $routerId,
        private readonly int $chunkSize = 200,
    ) {
        $this->onQueue(config('automation.queues.monitoring', 'monitoring'));
    }

    public function handle(PppoeMonitorSyncService $syncService): void
    {
        $router = Router::query()->find($this->routerId);

        if ($router === null) {
            $this->automationLogger()->warning('Router not found for PPPoE monitor sync job.', [
                'router_id' => $this->routerId,
            ]);

            return;
        }

        $this->logAutomationStarted('monitoring.sync_pppoe', [
            'router_id' => $router->id,
            'router_code' => $router->router_code,
            'chunk_size' => $this->chunkSize,
        ]);

        $summary = $syncService->syncRouter($router, $this->chunkSize);

        $this->logAutomationFinished('monitoring.sync_pppoe', $summary);
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('monitoring.sync_pppoe', $exception, [
            'router_id' => $this->routerId,
            'chunk_size' => $this->chunkSize,
        ]);
    }
}
