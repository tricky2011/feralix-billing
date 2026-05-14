<?php

namespace App\Services\Billing;

use App\Enums\InvoicePaymentStatus;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

class InvoicePaymentStatusService
{
    public function __construct(private readonly ServiceBillingStatusService $serviceBillingStatusService) {}

    public function resolve(
        string|float $amountPaid,
        string|float $totalAmount,
        ?Carbon $dueDate,
        bool $issued,
        ?Carbon $referenceDate = null,
    ): InvoicePaymentStatus {
        $referenceDate ??= now()->startOfDay();

        // Konversi ke string untuk bcmath, gunakan presisi 2 desimal
        $paidStr = is_string($amountPaid) ? $amountPaid : number_format($amountPaid, 2, '.', '');
        $totalStr = is_string($totalAmount) ? $totalAmount : number_format($totalAmount, 2, '.', '');

        // bcmath comparison: returns -1 if left < right, 0 if equal, 1 if left > right
        $isPaid = bccomp($paidStr, $totalStr, 2) >= 0;
        $isZero = bccomp($totalStr, '0', 2) <= 0;

        if ($isZero || $isPaid) {
            return InvoicePaymentStatus::Paid;
        }

        $hasAmountPaid = bccomp($paidStr, '0', 2) > 0;
        $isOverdue = $dueDate !== null
            && $dueDate->copy()->startOfDay()->lt($referenceDate->copy()->startOfDay())
            && ($issued || $hasAmountPaid);

        if ($isOverdue) {
            return InvoicePaymentStatus::Overdue;
        }

        if ($hasAmountPaid) {
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

    private function isIssued(Invoice $invoice, string|float $amountPaid): bool
    {
        $paidStr = is_string($amountPaid) ? $amountPaid : number_format($amountPaid, 2, '.', '');
        $hasAmountPaid = bccomp($paidStr, '0', 2) > 0;

        if ($invoice->issued_at !== null || $hasAmountPaid) {
            return true;
        }

        return in_array($invoice->payment_status?->value, InvoicePaymentStatus::issuedValues(), true);
    }
}
