<?php

namespace App\Services\Billing;

use App\Enums\ServiceIsolationType;
use App\Models\Invoice;
use App\Models\ServiceIsolation;
use App\Services\Provisioning\ServiceIsolationService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoiceIsolationAutomationService
{
    use InteractsWithAutomationLogging;

    public function __construct(private readonly ServiceIsolationService $serviceIsolationService) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function syncForInvoice(int $invoiceId, array $context = []): array
    {
        $invoice = Invoice::query()
            ->with([
                'service:id,customer_id,router_id,vid_id,service_code,subnet_cidr,address_list_name',
                'service.vid:id,subnet_cidr',
            ])
            ->find($invoiceId);

        if ($invoice === null) {
            return $this->logSkipped('skipped_missing_invoice', [
                'invoice_id' => $invoiceId,
                ...$context,
            ]);
        }

        if ($invoice->service === null) {
            return $this->logSkipped('skipped_missing_service', [
                'invoice_id' => $invoice->id,
                'service_id' => $invoice->service_id,
                ...$context,
            ]);
        }

        $service = $invoice->service;
        $overdueInvoices = $this->overdueInvoicesForService($service->id);
        $openBillingIsolations = $this->openBillingIsolationsForService($service->id);
        $hasAnyOpenIsolation = $this->hasAnyOpenIsolation($service->id);

        if ($overdueInvoices->isNotEmpty()) {
            return $this->ensureIsolationPresent(
                $invoice,
                $overdueInvoices,
                $openBillingIsolations,
                $hasAnyOpenIsolation,
                $context,
            );
        }

        return $this->ensureIsolationReleased($invoice, $openBillingIsolations, $context);
    }

    /**
     * @param  Collection<int, Invoice>  $overdueInvoices
     * @param  Collection<int, ServiceIsolation>  $openBillingIsolations
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function ensureIsolationPresent(
        Invoice $triggerInvoice,
        Collection $overdueInvoices,
        Collection $openBillingIsolations,
        bool $hasAnyOpenIsolation,
        array $context,
    ): array {
        $service = $triggerInvoice->service;
        $referenceInvoice = $overdueInvoices->firstWhere('id', $triggerInvoice->id) ?? $overdueInvoices->first();

        if ($referenceInvoice === null) {
            return $this->logSkipped('skipped_missing_reference_invoice', [
                'invoice_id' => $triggerInvoice->id,
                'service_id' => $service->id,
                ...$context,
            ]);
        }

        if ($service->router_id === null) {
            return $this->logSkipped('skipped_missing_router', [
                'invoice_id' => $triggerInvoice->id,
                'service_id' => $service->id,
                'reference_invoice_id' => $referenceInvoice->id,
                ...$context,
            ]);
        }

        if ($service->isolationTargetSubnet() === null) {
            return $this->logSkipped('skipped_missing_target_subnet', [
                'invoice_id' => $triggerInvoice->id,
                'service_id' => $service->id,
                'reference_invoice_id' => $referenceInvoice->id,
                ...$context,
            ]);
        }

        if ($openBillingIsolations->isNotEmpty()) {
            return $this->logSkipped('kept_existing_billing_isolation', [
                'invoice_id' => $triggerInvoice->id,
                'service_id' => $service->id,
                'reference_invoice_id' => $referenceInvoice->id,
                'open_isolation_ids' => $openBillingIsolations->pluck('id')->all(),
                'overdue_invoice_ids' => $overdueInvoices->pluck('id')->all(),
                ...$context,
            ]);
        }

        if ($hasAnyOpenIsolation) {
            return $this->logSkipped('skipped_open_isolation_exists', [
                'invoice_id' => $triggerInvoice->id,
                'service_id' => $service->id,
                'reference_invoice_id' => $referenceInvoice->id,
                'overdue_invoice_ids' => $overdueInvoices->pluck('id')->all(),
                ...$context,
            ]);
        }

        try {
            $isolation = $this->serviceIsolationService->createIsolationRecord([
                'service_id' => $service->id,
                'router_id' => $service->router_id,
                'invoice_id' => $referenceInvoice->id,
                'isolation_type' => ServiceIsolationType::InvoiceOverdue->value,
                'notes' => $this->buildIsolationNotes($triggerInvoice, $referenceInvoice),
            ]);
        } catch (ValidationException $exception) {
            return $this->logSkipped('skipped_validation', [
                'invoice_id' => $triggerInvoice->id,
                'service_id' => $service->id,
                'reference_invoice_id' => $referenceInvoice->id,
                'message' => $exception->getMessage(),
                ...$context,
            ]);
        } catch (Throwable $throwable) {
            $this->automationLogger()->error('billing.invoice_isolation.isolate_failed', [
                'invoice_id' => $triggerInvoice->id,
                'service_id' => $service->id,
                'reference_invoice_id' => $referenceInvoice->id,
                'exception' => get_class($throwable),
                'message' => $throwable->getMessage(),
                ...$context,
            ]);

            throw $throwable;
        }

        $summary = [
            'action' => 'isolate_queued',
            'invoice_id' => $triggerInvoice->id,
            'service_id' => $service->id,
            'reference_invoice_id' => $referenceInvoice->id,
            'service_isolation_id' => $isolation->id,
            'overdue_invoice_ids' => $overdueInvoices->pluck('id')->all(),
            ...$context,
        ];

        $this->automationLogger()->info('billing.invoice_isolation.isolate_queued', $summary);

        return $summary;
    }

    /**
     * @param  Collection<int, ServiceIsolation>  $openBillingIsolations
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function ensureIsolationReleased(
        Invoice $triggerInvoice,
        Collection $openBillingIsolations,
        array $context,
    ): array {
        if ($openBillingIsolations->isEmpty()) {
            return $this->logSkipped('skipped_no_billing_isolation_to_release', [
                'invoice_id' => $triggerInvoice->id,
                'service_id' => $triggerInvoice->service_id,
                ...$context,
            ]);
        }

        $releasedIsolationIds = [];

        foreach ($openBillingIsolations as $isolation) {
            try {
                $releasedIsolation = $this->serviceIsolationService->releaseIsolation($isolation, [
                    'released_at' => now(),
                    'notes' => $this->buildReleaseNotes($triggerInvoice, $isolation),
                ]);
            } catch (ValidationException $exception) {
                $this->automationLogger()->info('billing.invoice_isolation.release_skipped', [
                    'invoice_id' => $triggerInvoice->id,
                    'service_id' => $triggerInvoice->service_id,
                    'service_isolation_id' => $isolation->id,
                    'message' => $exception->getMessage(),
                    ...$context,
                ]);

                continue;
            } catch (Throwable $throwable) {
                $this->automationLogger()->error('billing.invoice_isolation.release_failed', [
                    'invoice_id' => $triggerInvoice->id,
                    'service_id' => $triggerInvoice->service_id,
                    'service_isolation_id' => $isolation->id,
                    'exception' => get_class($throwable),
                    'message' => $throwable->getMessage(),
                    ...$context,
                ]);

                throw $throwable;
            }

            $releasedIsolationIds[] = $releasedIsolation->id;

            $this->automationLogger()->info('billing.invoice_isolation.release_queued', [
                'invoice_id' => $triggerInvoice->id,
                'service_id' => $triggerInvoice->service_id,
                'service_isolation_id' => $releasedIsolation->id,
                'reference_invoice_id' => $releasedIsolation->invoice_id,
                ...$context,
            ]);
        }

        return [
            'action' => $releasedIsolationIds === []
                ? 'skipped_release_validation'
                : 'release_queued',
            'invoice_id' => $triggerInvoice->id,
            'service_id' => $triggerInvoice->service_id,
            'released_isolation_ids' => $releasedIsolationIds,
            ...$context,
        ];
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function overdueInvoicesForService(int $serviceId): Collection
    {
        return Invoice::query()
            ->where('service_id', $serviceId)
            ->overdue()
            ->orderBy('due_date')
            ->orderBy('id')
            ->get(['id', 'service_id', 'invoice_number', 'due_date', 'payment_status']);
    }

    /**
     * @return Collection<int, ServiceIsolation>
     */
    private function openBillingIsolationsForService(int $serviceId): Collection
    {
        return ServiceIsolation::query()
            ->where('service_id', $serviceId)
            ->where('isolation_type', ServiceIsolationType::InvoiceOverdue->value)
            ->open()
            ->orderBy('id')
            ->get();
    }

    private function hasAnyOpenIsolation(int $serviceId): bool
    {
        return ServiceIsolation::query()
            ->where('service_id', $serviceId)
            ->open()
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function logSkipped(string $action, array $context): array
    {
        $summary = [
            'action' => $action,
            ...$context,
        ];

        $this->automationLogger()->info('billing.invoice_isolation.skipped', $summary);

        return $summary;
    }

    private function buildIsolationNotes(Invoice $triggerInvoice, Invoice $referenceInvoice): string
    {
        $notes = trim((string) config('automation.billing.service_isolation.notes'));

        return collect([
            $notes !== '' ? $notes : null,
            sprintf(
                'Auto isolate from billing hook. trigger_invoice_id:%d reference_invoice_id:%d',
                $triggerInvoice->id,
                $referenceInvoice->id,
            ),
        ])->filter()->implode("\n");
    }

    private function buildReleaseNotes(Invoice $triggerInvoice, ServiceIsolation $isolation): string
    {
        return sprintf(
            'Auto release from billing hook. trigger_invoice_id:%d reference_invoice_id:%d',
            $triggerInvoice->id,
            $isolation->invoice_id,
        );
    }
}
