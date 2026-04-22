<?php

namespace App\Jobs;

use App\Enums\MikrotikSyncJobStatus;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikVidSyncService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class SyncMikrotikVidJob implements ShouldQueue
{
    use Dispatchable, InteractsWithAutomationLogging, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly int $routerId,
        private readonly ?string $providerName = null,
    ) {
        $this->onQueue(config('automation.queues.network', 'network'));
    }

    public function handle(MikrotikVidSyncService $syncService): void
    {
        $router = Router::query()->find($this->routerId);

        if ($router === null) {
            $this->automationLogger()->warning('Router not found for Mikrotik VID sync job.', [
                'router_id' => $this->routerId,
            ]);

            return;
        }

        $this->logAutomationStarted('network.sync_mikrotik_vid', [
            'router_id' => $router->id,
            'router_code' => $router->router_code,
            'provider' => $this->providerName,
        ]);

        $job = $syncService->sync($router, $this->providerName);

        $this->logAutomationFinished('network.sync_mikrotik_vid', [
            'router_id' => $router->id,
            'router_code' => $router->router_code,
            'job_status' => $job->job_status?->value,
            'total_found' => $job->total_found,
            'total_new' => $job->total_new,
            'total_updated' => $job->total_updated,
            'total_conflict' => $job->total_conflict,
        ]);

        if ($job->job_status !== MikrotikSyncJobStatus::Completed) {
            throw new RuntimeException($job->summary ?? 'Mikrotik VID sync finished with a non-completed status.');
        }
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('network.sync_mikrotik_vid', $exception, [
            'router_id' => $this->routerId,
            'provider' => $this->providerName,
        ]);
    }
}
