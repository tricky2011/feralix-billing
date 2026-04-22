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

class CheckOverdueInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithAutomationLogging, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly ?string $referenceDate = null,
        private readonly int $chunkSize = 200,
    ) {
        $this->onQueue(config('automation.queues.billing', 'billing'));
    }

    public function handle(BillingAutomationService $billingAutomationService): void
    {
        $this->logAutomationStarted('billing.check_overdue_invoices', [
            'reference_date' => $this->referenceDate,
            'chunk_size' => $this->chunkSize,
        ]);

        $summary = $billingAutomationService->syncOverdueStatuses(
            referenceDate: $this->referenceDate === null ? null : Carbon::parse($this->referenceDate)->startOfDay(),
            chunkSize: $this->chunkSize,
        );

        $this->logAutomationFinished('billing.check_overdue_invoices', $summary);
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('billing.check_overdue_invoices', $exception, [
            'reference_date' => $this->referenceDate,
            'chunk_size' => $this->chunkSize,
        ]);
    }
}
