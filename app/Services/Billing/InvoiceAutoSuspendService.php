<?php

namespace App\Services\Billing;

use App\Enums\ServiceIsolationType;
use App\Models\Invoice;
use App\Models\ServiceIsolation;
use App\Services\Access\RoleRouterScopeService;
use App\Services\Provisioning\ServiceIsolationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoiceAutoSuspendService
{
    public function __construct(
        private readonly ServiceIsolationService $serviceIsolationService,
        private readonly RoleRouterScopeService $roleRouterScopeService,
    ) {}

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
    public function trigger(array $filters = [], ?Carbon $referenceDate = null, int $chunkSize = 100): array
    {
        $referenceDate ??= now()->startOfDay();
        $notes = trim((string) config('automation.billing.service_isolation.notes'));
        $summary = [
            'reference_date' => $referenceDate->toDateString(),
            'checked_invoices' => 0,
            'created_isolations' => 0,
            'skipped_existing_open_isolation' => 0,
            'skipped_missing_router' => 0,
            'skipped_missing_target_subnet' => 0,
            'failed' => 0,
        ];

        if (($filters['router_id'] ?? null) !== null) {
            $this->roleRouterScopeService->ensureRouterAccessible((int) $filters['router_id']);
        }

        $query = Invoice::query()
            ->overdue($referenceDate)
            ->when(
                $filters['customer_id'] ?? null,
                fn (Builder $builder, $customerId) => $builder->where('customer_id', $customerId),
            )
            ->when(
                $filters['service_id'] ?? null,
                fn (Builder $builder, $serviceId) => $builder->where('service_id', $serviceId),
            )
            ->router(isset($filters['router_id']) ? (int) $filters['router_id'] : null)
            ->select('id')
            ->orderBy('id');

        $this->roleRouterScopeService->applyServiceRouterScope($query, 'service', 'router_id');

        $query
            ->chunkById($chunkSize, function ($invoiceReferences) use (&$summary, $notes): void {
                foreach ($invoiceReferences as $invoiceReference) {
                    $summary['checked_invoices']++;

                    $invoice = Invoice::query()
                        ->with(['service.vid:id,subnet_cidr'])
                        ->find($invoiceReference->id);

                    if ($invoice === null || $invoice->service === null) {
                        $summary['failed']++;

                        continue;
                    }

                    if ($invoice->service->router_id === null) {
                        $summary['skipped_missing_router']++;

                        continue;
                    }

                    if ($invoice->service->isolationTargetSubnet() === null) {
                        $summary['skipped_missing_target_subnet']++;

                        continue;
                    }

                    $hasOpenIsolation = ServiceIsolation::query()
                        ->where('service_id', $invoice->service_id)
                        ->open()
                        ->exists();

                    if ($hasOpenIsolation) {
                        $summary['skipped_existing_open_isolation']++;

                        continue;
                    }

                    try {
                        $this->serviceIsolationService->createIsolationRecord([
                            'service_id' => $invoice->service_id,
                            'router_id' => $invoice->service->router_id,
                            'invoice_id' => $invoice->id,
                            'isolation_type' => ServiceIsolationType::InvoiceOverdue->value,
                            'notes' => $notes !== '' ? $notes : null,
                        ]);

                        $summary['created_isolations']++;
                    } catch (ValidationException) {
                        $summary['skipped_existing_open_isolation']++;
                    } catch (Throwable $throwable) {
                        report($throwable);
                        $summary['failed']++;
                    }
                }
            });

        return $summary;
    }
}
