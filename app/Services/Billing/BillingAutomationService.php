<?php

namespace App\Services\Billing;

use App\Enums\ServiceBillingStatus;
use App\Models\Invoice;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BillingAutomationService
{
    public function __construct(
        private readonly ServiceBillingStatusService $serviceBillingStatusService,
        private readonly InvoiceOverdueService $invoiceOverdueService,
        private readonly InvoiceAutoSuspendService $invoiceAutoSuspendService,
    ) {}

    /**
     * @return array{
     *     reference_date:string,
     *     overdue_invoices:int,
     *     checked_services:int,
     *     updated_services:int,
     *     overdue_services:int,
     *     pending_services:int,
     *     paid_services:int
     * }
     */
    public function syncOverdueStatuses(?Carbon $referenceDate = null, int $chunkSize = 200): array
    {
        $referenceDate ??= now()->startOfDay();
        $overdueResult = $this->invoiceOverdueService->markDueInvoices(
            referenceDate: $referenceDate,
            chunkSize: $chunkSize,
        );
        $summary = [
            'reference_date' => $referenceDate->toDateString(),
            'overdue_invoices' => Invoice::query()->overdue($referenceDate)->count(),
            'marked_overdue_invoices' => $overdueResult['marked_overdue_invoices'],
            'checked_services' => 0,
            'updated_services' => 0,
            'overdue_services' => 0,
            'pending_services' => 0,
            'paid_services' => 0,
        ];

        Service::query()
            ->whereHas('invoices')
            ->select('id')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($services) use (&$summary, $referenceDate): void {
                foreach ($services as $serviceReference) {
                    $result = DB::transaction(function () use ($serviceReference, $referenceDate): ?array {
                        $service = Service::query()
                            ->lockForUpdate()
                            ->find($serviceReference->id);

                        if ($service === null) {
                            return null;
                        }

                        $previousStatus = $service->billing_status;
                        $currentStatus = $this->serviceBillingStatusService->sync($service, $referenceDate);

                        return [
                            'previous_status' => $previousStatus instanceof ServiceBillingStatus
                                ? $previousStatus
                                : ServiceBillingStatus::tryFrom((string) $previousStatus),
                            'current_status' => $currentStatus,
                        ];
                    });

                    if ($result === null) {
                        continue;
                    }

                    $summary['checked_services']++;

                    if (($result['previous_status'] ?? null) !== $result['current_status']) {
                        $summary['updated_services']++;
                    }

                    match ($result['current_status']) {
                        ServiceBillingStatus::Overdue => $summary['overdue_services']++,
                        ServiceBillingStatus::Pending => $summary['pending_services']++,
                        ServiceBillingStatus::Paid => $summary['paid_services']++,
                        default => null,
                    };
                }
            });

        return $summary;
    }

    /**
     * @return array{
     *     reference_date:string,
     *     checked_invoices:int,
     *     created_isolations:int,
     *     skipped_existing_open_isolation:int,
     *     skipped_missing_router:int,
     *     skipped_missing_target_subnet:int,
     *     failed:int
     * }
     */
    public function createOverdueIsolationRecords(?Carbon $referenceDate = null, int $chunkSize = 100): array
    {
        return $this->invoiceAutoSuspendService->trigger(
            referenceDate: $referenceDate,
            chunkSize: $chunkSize,
        );
    }
}
