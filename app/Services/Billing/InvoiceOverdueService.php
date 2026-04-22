<?php

namespace App\Services\Billing;

use App\Enums\InvoicePaymentStatus;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoiceOverdueService
{
    public function __construct(private readonly ServiceBillingStatusService $serviceBillingStatusService) {}

    public function mark(Invoice $invoice, array $payload = []): Invoice
    {
        return DB::transaction(function () use ($invoice, $payload): Invoice {
            $referenceDate = isset($payload['reference_date'])
                ? Carbon::parse($payload['reference_date'])
                : now();
            $force = (bool) ($payload['force'] ?? false);

            $lockedInvoice = Invoice::query()
                ->with('service')
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($lockedInvoice->payment_status?->isSettled() === true) {
                throw ValidationException::withMessages([
                    'invoice' => 'Paid invoices cannot be marked overdue.',
                ]);
            }

            if (! $force && ($lockedInvoice->due_date === null || ! $lockedInvoice->due_date->copy()->startOfDay()->lt($referenceDate->copy()->startOfDay()))) {
                throw ValidationException::withMessages([
                    'invoice' => 'The invoice due date has not passed yet.',
                ]);
            }

            $lockedInvoice->update([
                'payment_status' => InvoicePaymentStatus::Overdue->value,
                'issued_at' => $lockedInvoice->issued_at ?? $lockedInvoice->invoice_date?->copy()->startOfDay() ?? $referenceDate,
                'overdue_marked_at' => $lockedInvoice->overdue_marked_at ?? $referenceDate,
                'status_changed_at' => $referenceDate,
            ]);

            if ($lockedInvoice->service !== null) {
                $this->serviceBillingStatusService->sync(
                    $lockedInvoice->service,
                    $referenceDate->copy()->startOfDay(),
                );
            }

            return $lockedInvoice->refresh();
        });
    }

    /**
     * @return array{reference_date:string,checked_invoices:int,marked_overdue_invoices:int,unchanged:int,failed:int}
     */
    public function markDueInvoices(array $filters = [], ?Carbon $referenceDate = null, int $chunkSize = 200): array
    {
        $referenceDate ??= now()->startOfDay();
        $summary = [
            'reference_date' => $referenceDate->toDateString(),
            'checked_invoices' => 0,
            'marked_overdue_invoices' => 0,
            'unchanged' => 0,
            'failed' => 0,
        ];

        Invoice::query()
            ->whereIn('payment_status', [
                InvoicePaymentStatus::Unpaid->value,
                InvoicePaymentStatus::Issued->value,
                InvoicePaymentStatus::PartiallyPaid->value,
            ])
            ->whereDate('due_date', '<', $referenceDate->toDateString())
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
            ->orderBy('id')
            ->chunkById($chunkSize, function ($invoiceReferences) use (&$summary, $referenceDate): void {
                foreach ($invoiceReferences as $invoiceReference) {
                    $summary['checked_invoices']++;

                    try {
                        $result = DB::transaction(function () use ($invoiceReference, $referenceDate): bool {
                            $invoice = Invoice::query()
                                ->with('service')
                                ->lockForUpdate()
                                ->find($invoiceReference->id);

                            if ($invoice === null || $invoice->payment_status === InvoicePaymentStatus::Overdue) {
                                return false;
                            }

                            $invoice->update([
                                'payment_status' => InvoicePaymentStatus::Overdue->value,
                                'issued_at' => $invoice->issued_at ?? $invoice->invoice_date?->copy()->startOfDay() ?? $referenceDate,
                                'overdue_marked_at' => $invoice->overdue_marked_at ?? $referenceDate,
                                'status_changed_at' => $referenceDate,
                            ]);

                            if ($invoice->service !== null) {
                                $this->serviceBillingStatusService->sync(
                                    $invoice->service,
                                    $referenceDate->copy()->startOfDay(),
                                );
                            }

                            return true;
                        });

                        if ($result) {
                            $summary['marked_overdue_invoices']++;
                        } else {
                            $summary['unchanged']++;
                        }
                    } catch (Throwable $throwable) {
                        report($throwable);
                        $summary['failed']++;
                    }
                }
            });

        return $summary;
    }
}
