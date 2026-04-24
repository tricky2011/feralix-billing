<?php

namespace App\Jobs;

use App\Services\Billing\InvoiceIsolationAutomationService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncInvoiceServiceIsolationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithAutomationLogging, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly int $invoiceId,
        private readonly array $context = [],
    ) {
        $this->afterCommit();
        $this->onQueue(config('automation.queues.provisioning', 'provisioning'));
    }

    public function handle(InvoiceIsolationAutomationService $automationService): void
    {
        $this->logAutomationStarted('billing.sync_invoice_service_isolation', [
            'invoice_id' => $this->invoiceId,
            ...$this->context,
        ]);

        $summary = $automationService->syncForInvoice($this->invoiceId, $this->context);

        $this->logAutomationFinished('billing.sync_invoice_service_isolation', [
            'invoice_id' => $this->invoiceId,
            ...$summary,
        ]);
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('billing.sync_invoice_service_isolation', $exception, [
            'invoice_id' => $this->invoiceId,
            ...$this->context,
        ]);
    }
}
