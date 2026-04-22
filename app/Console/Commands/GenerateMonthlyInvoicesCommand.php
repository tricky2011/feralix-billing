<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyInvoicesJob;
use App\Services\Billing\InvoiceService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

class GenerateMonthlyInvoicesCommand extends Command
{
    use InteractsWithAutomationLogging;

    protected $signature = 'billing:generate-monthly-invoices
        {billing_period? : Billing period in Y-m format, defaults to the current month}
        {--invoice-date= : Invoice date in Y-m-d format}
        {--due-date= : Due date in Y-m-d format}
        {--due-in-days= : Due date offset in days when due date is not provided}
        {--penalty-amount= : Penalty amount added to generated invoices}
        {--customer= : Limit generation to a specific customer ID}
        {--service= : Limit generation to a specific service ID}
        {--queue : Dispatch the generation to the billing queue}';

    protected $description = 'Generate monthly invoices for billable services.';

    public function handle(InvoiceService $invoiceService): int
    {
        try {
            $payload = $this->buildPayload();
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('queue')) {
            GenerateMonthlyInvoicesJob::dispatch($payload);

            $this->info(sprintf(
                'Queued monthly invoice generation for billing period %s.',
                $payload['billing_period'],
            ));

            return self::SUCCESS;
        }

        try {
            $result = $invoiceService->generateMonthlyInvoices($payload);
        } catch (Throwable $throwable) {
            report($throwable);
            $this->logAutomationFailed('billing.generate_monthly_invoices.command', $throwable, [
                'billing_period' => $payload['billing_period'],
            ]);
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Billing period %s: generated %d invoice(s), skipped %d, due date %s.',
            $result['billing_period'],
            $result['generated_count'],
            $result['skipped_count'],
            $result['due_date'],
        ));

        return self::SUCCESS;
    }

    private function buildPayload(): array
    {
        $billingPeriod = $this->argument('billing_period') !== null
            ? trim((string) $this->argument('billing_period'))
            : now()->format('Y-m');

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $billingPeriod)) {
            throw new InvalidArgumentException('The billing period must use Y-m format.');
        }

        $invoiceDate = $this->normalizeDateOption('invoice-date');
        $dueDate = $this->normalizeDateOption('due-date');
        $dueInDays = $this->option('due-in-days') !== null
            ? max(0, (int) $this->option('due-in-days'))
            : (int) config('automation.billing.monthly_invoice.due_in_days', 10);
        $penaltyAmount = $this->option('penalty-amount') !== null
            ? (string) $this->option('penalty-amount')
            : (string) config('automation.billing.monthly_invoice.penalty_amount', 0);

        return array_filter([
            'billing_period' => $billingPeriod,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'due_in_days' => $dueDate === null ? $dueInDays : null,
            'penalty_amount' => $penaltyAmount,
            'customer_id' => $this->option('customer') !== null ? (int) $this->option('customer') : null,
            'service_id' => $this->option('service') !== null ? (int) $this->option('service') : null,
        ], static fn ($value): bool => $value !== null);
    }

    private function normalizeDateOption(string $option): ?string
    {
        if ($this->option($option) === null || trim((string) $this->option($option)) === '') {
            return null;
        }

        return Carbon::parse((string) $this->option($option))->toDateString();
    }
}
