<?php

namespace App\Jobs;

use App\Services\Billing\BillingAutomationService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class CreateOverdueServiceIsolationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithAutomationLogging, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly ?string $referenceDate = null,
        private readonly int $chunkSize = 100,
    ) {
        $this->onQueue(config('automation.queues.provisioning', 'provisioning'));
    }

    public function handle(BillingAutomationService $billingAutomationService): void
    {
        $this->logAutomationStarted('billing.create_overdue_service_isolations', [
            'reference_date' => $this->referenceDate,
            'chunk_size' => $this->chunkSize,
        ]);

        $summary = $billingAutomationService->createOverdueIsolationRecords(
            referenceDate: $this->referenceDate === null ? null : Carbon::parse($this->referenceDate)->startOfDay(),
            chunkSize: $this->chunkSize,
        );

        $this->logAutomationFinished('billing.create_overdue_service_isolations', $summary);
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('billing.create_overdue_service_isolations', $exception, [
            'reference_date' => $this->referenceDate,
            'chunk_size' => $this->chunkSize,
        ]);
    }
}
