<?php

namespace App\Jobs;

use App\Models\Ont;
use App\Services\GenieAcs\GenieAcsOntSyncService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncGenieAcsOntJob implements ShouldQueue
{
    use Dispatchable, InteractsWithAutomationLogging, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly ?int $ontId = null,
        private readonly ?string $providerName = null,
        private readonly int $chunkSize = 100,
    ) {
        $this->onQueue(config('automation.queues.monitoring', 'monitoring'));
    }

    public function handle(GenieAcsOntSyncService $syncService): void
    {
        $this->logAutomationStarted('monitoring.sync_genieacs_ont', [
            'ont_id' => $this->ontId,
            'provider' => $this->providerName,
            'chunk_size' => $this->chunkSize,
        ]);

        if ($this->ontId !== null) {
            $ont = Ont::query()->find($this->ontId);

            if ($ont === null) {
                $this->automationLogger()->warning('ONT not found for GenieACS sync job.', [
                    'ont_id' => $this->ontId,
                ]);

                return;
            }

            $result = $syncService->syncOnt($ont, $this->providerName);

            $this->logAutomationFinished('monitoring.sync_genieacs_ont', $result);

            return;
        }

        $summary = $syncService->syncQuery(
            query: Ont::query(),
            providerName: $this->providerName,
            chunkSize: $this->chunkSize,
        );

        $this->logAutomationFinished('monitoring.sync_genieacs_ont', $summary);
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('monitoring.sync_genieacs_ont', $exception, [
            'ont_id' => $this->ontId,
            'provider' => $this->providerName,
            'chunk_size' => $this->chunkSize,
        ]);
    }
}
