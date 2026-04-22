<?php

namespace App\Jobs;

use App\Services\Billing\InvoiceService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateMonthlyInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithAutomationLogging, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(private readonly array $payload)
    {
        $this->onQueue(config('automation.queues.billing', 'billing'));
    }

    public function handle(InvoiceService $invoiceService): void
    {
        $this->logAutomationStarted('billing.generate_monthly_invoices', [
            'billing_period' => $this->payload['billing_period'] ?? null,
        ]);

        $result = $invoiceService->generateMonthlyInvoices($this->payload);

        $this->logAutomationFinished('billing.generate_monthly_invoices', [
            'billing_period' => $result['billing_period'],
            'generated_count' => $result['generated_count'],
            'skipped_count' => $result['skipped_count'],
            'due_date' => $result['due_date'],
        ]);
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('billing.generate_monthly_invoices', $exception, [
            'billing_period' => $this->payload['billing_period'] ?? null,
        ]);
    }
}
