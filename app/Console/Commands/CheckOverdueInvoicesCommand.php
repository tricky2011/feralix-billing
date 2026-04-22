<?php

namespace App\Console\Commands;

use App\Jobs\CheckOverdueInvoicesJob;
use App\Services\Billing\BillingAutomationService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class CheckOverdueInvoicesCommand extends Command
{
    use InteractsWithAutomationLogging;

    protected $signature = 'billing:check-overdue-invoices
        {--date= : Reference date in Y-m-d format, defaults to today}
        {--chunk= : Number of services to process per chunk}
        {--queue : Dispatch the overdue check to the billing queue}';

    protected $description = 'Recalculate service billing status based on overdue invoices.';

    public function handle(BillingAutomationService $billingAutomationService): int
    {
        $referenceDate = $this->normalizeReferenceDate();
        $chunkSize = max(1, (int) ($this->option('chunk') ?: config('automation.billing.overdue.chunk', 200)));

        if ((bool) $this->option('queue')) {
            CheckOverdueInvoicesJob::dispatch($referenceDate?->toDateString(), $chunkSize);

            $this->info(sprintf(
                'Queued overdue invoice check for %s.',
                $referenceDate?->toDateString() ?? now()->toDateString(),
            ));

            return self::SUCCESS;
        }

        try {
            $summary = $billingAutomationService->syncOverdueStatuses($referenceDate, $chunkSize);
        } catch (Throwable $throwable) {
            report($throwable);
            $this->logAutomationFailed('billing.check_overdue_invoices.command', $throwable, [
                'reference_date' => $referenceDate?->toDateString(),
                'chunk_size' => $chunkSize,
            ]);
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Reference date %s: overdue invoices %d, services checked %d, updated %d, overdue %d, pending %d, paid %d.',
            $summary['reference_date'],
            $summary['overdue_invoices'],
            $summary['checked_services'],
            $summary['updated_services'],
            $summary['overdue_services'],
            $summary['pending_services'],
            $summary['paid_services'],
        ));

        return self::SUCCESS;
    }

    private function normalizeReferenceDate(): ?Carbon
    {
        if ($this->option('date') === null || trim((string) $this->option('date')) === '') {
            return null;
        }

        return Carbon::parse((string) $this->option('date'))->startOfDay();
    }
}
