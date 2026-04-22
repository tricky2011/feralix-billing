<?php

namespace App\Console\Commands;

use App\Jobs\CreateOverdueServiceIsolationJob;
use App\Services\Billing\BillingAutomationService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class CreateOverdueServiceIsolationCommand extends Command
{
    use InteractsWithAutomationLogging;

    protected $signature = 'billing:create-overdue-isolations
        {--date= : Reference date in Y-m-d format, defaults to today}
        {--chunk= : Number of overdue invoices to process per chunk}
        {--queue : Dispatch isolation creation to the provisioning queue}';

    protected $description = 'Create service isolation records for overdue invoices.';

    public function handle(BillingAutomationService $billingAutomationService): int
    {
        $referenceDate = $this->normalizeReferenceDate();
        $chunkSize = max(1, (int) ($this->option('chunk') ?: config('automation.billing.service_isolation.chunk', 100)));

        if ((bool) $this->option('queue')) {
            CreateOverdueServiceIsolationJob::dispatch($referenceDate?->toDateString(), $chunkSize);

            $this->info(sprintf(
                'Queued overdue isolation creation for %s.',
                $referenceDate?->toDateString() ?? now()->toDateString(),
            ));

            return self::SUCCESS;
        }

        try {
            $summary = $billingAutomationService->createOverdueIsolationRecords($referenceDate, $chunkSize);
        } catch (Throwable $throwable) {
            report($throwable);
            $this->logAutomationFailed('billing.create_overdue_service_isolations.command', $throwable, [
                'reference_date' => $referenceDate?->toDateString(),
                'chunk_size' => $chunkSize,
            ]);
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Reference date %s: checked %d invoice(s), created %d isolation(s), skipped open %d, skipped router %d, skipped subnet %d, failed %d.',
            $summary['reference_date'],
            $summary['checked_invoices'],
            $summary['created_isolations'],
            $summary['skipped_existing_open_isolation'],
            $summary['skipped_missing_router'],
            $summary['skipped_missing_target_subnet'],
            $summary['failed'],
        ));

        return $summary['failed'] === 0
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function normalizeReferenceDate(): ?Carbon
    {
        if ($this->option('date') === null || trim((string) $this->option('date')) === '') {
            return null;
        }

        return Carbon::parse((string) $this->option('date'))->startOfDay();
    }
}
