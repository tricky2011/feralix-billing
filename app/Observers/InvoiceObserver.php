<?php

namespace App\Observers;

use App\Enums\InvoicePaymentStatus;
use App\Jobs\SyncInvoiceServiceIsolationJob;
use App\Models\Invoice;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        $currentStatus = $this->normalizeStatus($invoice->payment_status);

        if (! $this->shouldSyncOnCreate($currentStatus)) {
            return;
        }

        SyncInvoiceServiceIsolationJob::dispatch($invoice->id, [
            'trigger' => 'invoice_created',
            'current_status' => $currentStatus?->value,
        ]);
    }

    public function updated(Invoice $invoice): void
    {
        if (! $invoice->wasChanged('payment_status')) {
            return;
        }

        $previousStatus = $this->normalizeStatus($invoice->getOriginal('payment_status'));
        $currentStatus = $this->normalizeStatus($invoice->payment_status);

        if (! $this->shouldSyncOnUpdate($previousStatus, $currentStatus)) {
            return;
        }

        SyncInvoiceServiceIsolationJob::dispatch($invoice->id, [
            'trigger' => 'payment_status_changed',
            'previous_status' => $previousStatus?->value,
            'current_status' => $currentStatus?->value,
        ]);
    }

    private function shouldSyncOnCreate(?InvoicePaymentStatus $currentStatus): bool
    {
        return in_array($currentStatus, [
            InvoicePaymentStatus::Overdue,
            InvoicePaymentStatus::Paid,
        ], true);
    }

    private function shouldSyncOnUpdate(
        ?InvoicePaymentStatus $previousStatus,
        ?InvoicePaymentStatus $currentStatus,
    ): bool {
        return in_array($previousStatus, [
            InvoicePaymentStatus::Overdue,
            InvoicePaymentStatus::Paid,
        ], true) || in_array($currentStatus, [
            InvoicePaymentStatus::Overdue,
            InvoicePaymentStatus::Paid,
        ], true);
    }

    private function normalizeStatus(InvoicePaymentStatus|string|null $status): ?InvoicePaymentStatus
    {
        if ($status instanceof InvoicePaymentStatus) {
            return $status;
        }

        if (is_string($status) && $status !== '') {
            return InvoicePaymentStatus::tryFrom($status);
        }

        return null;
    }
}
