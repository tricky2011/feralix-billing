<?php

namespace App\Services\Billing;

use App\Enums\InvoicePaymentStatus;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

class InvoicePaymentStatusService
{
    public function __construct(private readonly ServiceBillingStatusService $serviceBillingStatusService) {}

    public function resolve(
        float $amountPaid,
        float $totalAmount,
        ?Carbon $dueDate,
        bool $issued,
        ?Carbon $referenceDate = null,
    ): InvoicePaymentStatus {
        $referenceDate ??= now()->startOfDay();

        if ($totalAmount <= 0 || $amountPaid >= $totalAmount) {
            return InvoicePaymentStatus::Paid;
        }

        if ($dueDate !== null && $dueDate->copy()->startOfDay()->lt($referenceDate->copy()->startOfDay()) && ($issued || $amountPaid > 0)) {
            return InvoicePaymentStatus::Overdue;
        }

        if ($amountPaid > 0) {
            return InvoicePaymentStatus::PartiallyPaid;
        }

        return $issued
            ? InvoicePaymentStatus::Issued
            : InvoicePaymentStatus::Unpaid;
    }

    public function sync(
        Invoice $invoice,
        ?float $amountPaid = null,
        ?Carbon $referenceDate = null,
        ?bool $issued = null,
    ): Invoice {
        $referenceDate ??= now();
        $invoice->loadMissing('service');

        $amountPaid ??= (float) $invoice->payments()->sum('amount_paid');
        $issued ??= $this->isIssued($invoice, $amountPaid);
        $status = $this->resolve(
            amountPaid: $amountPaid,
            totalAmount: (float) $invoice->total_amount,
            dueDate: $invoice->due_date?->copy()->startOfDay(),
            issued: $issued,
            referenceDate: $referenceDate->copy()->startOfDay(),
        );

        $invoice->update([
            'payment_status' => $status->value,
            'issued_at' => $status === InvoicePaymentStatus::Unpaid
                ? null
                : ($invoice->issued_at ?? $invoice->invoice_date?->copy()->startOfDay() ?? $referenceDate),
            'paid_at' => $status === InvoicePaymentStatus::Paid
                ? ($invoice->paid_at ?? $referenceDate)
                : null,
            'overdue_marked_at' => $status === InvoicePaymentStatus::Overdue
                ? ($invoice->overdue_marked_at ?? $referenceDate)
                : $invoice->overdue_marked_at,
            'status_changed_at' => $referenceDate,
        ]);

        if ($invoice->service !== null) {
            $this->serviceBillingStatusService->sync($invoice->service, $referenceDate->copy()->startOfDay());
        }

        return $invoice->refresh();
    }

    private function isIssued(Invoice $invoice, float $amountPaid): bool
    {
        if ($invoice->issued_at !== null || $amountPaid > 0) {
            return true;
        }

        return in_array($invoice->payment_status?->value, InvoicePaymentStatus::issuedValues(), true);
    }
}
