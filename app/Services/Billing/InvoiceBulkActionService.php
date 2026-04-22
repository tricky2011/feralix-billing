<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

class InvoiceBulkActionService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceOverdueService $invoiceOverdueService,
        private readonly PaymentService $paymentService,
        private readonly InvoiceWhatsappService $invoiceWhatsappService,
    ) {}

    public function handle(array $payload): array
    {
        $invoices = Invoice::query()
            ->whereIn('id', $payload['invoice_ids'])
            ->get()
            ->keyBy('id');

        $processed = [];
        $skipped = [];

        foreach ($payload['invoice_ids'] as $invoiceId) {
            $invoice = $invoices->get($invoiceId);

            if ($invoice === null) {
                continue;
            }

            try {
                $processed[] = match ($payload['action']) {
                    'mark_overdue' => $this->markOverdue($invoice, $payload),
                    'mark_paid' => $this->markPaid($invoice, $payload),
                    'delete' => $this->delete($invoice),
                    'send_whatsapp' => $this->sendWhatsapp($invoice, $payload),
                    default => throw ValidationException::withMessages([
                        'action' => 'Unsupported bulk invoice action.',
                    ]),
                };
            } catch (ValidationException $exception) {
                $skipped[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'reason' => collect($exception->errors())
                        ->flatten()
                        ->join(' '),
                ];
            }
        }

        return [
            'action' => $payload['action'],
            'requested_count' => count($payload['invoice_ids']),
            'processed_count' => count($processed),
            'skipped_count' => count($skipped),
            'processed' => $processed,
            'skipped' => $skipped,
        ];
    }

    private function markOverdue(Invoice $invoice, array $payload): array
    {
        $result = $this->invoiceOverdueService->mark($invoice, [
            'force' => $payload['force'] ?? false,
            'reference_date' => $payload['reference_date'] ?? null,
        ]);

        return [
            'invoice_id' => $result->id,
            'invoice_number' => $result->invoice_number,
            'payment_status' => $result->payment_status?->value,
        ];
    }

    private function markPaid(Invoice $invoice, array $payload): array
    {
        $payment = $this->paymentService->settleInvoice($invoice, [
            'payment_method' => $payload['payment_method'] ?? null,
            'paid_at' => $payload['paid_at'] ?? null,
            'reference_no' => $payload['reference_no'] ?? null,
            'created_by' => $payload['created_by'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ]);

        return [
            'invoice_id' => $payment->invoice_id,
            'invoice_number' => $payment->invoice?->invoice_number,
            'payment_id' => $payment->id,
            'payment_status' => $payment->invoice?->payment_status?->value,
        ];
    }

    private function delete(Invoice $invoice): array
    {
        $this->invoiceService->delete($invoice);

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'deleted' => true,
        ];
    }

    private function sendWhatsapp(Invoice $invoice, array $payload): array
    {
        $dispatch = $this->invoiceWhatsappService->send($invoice, [
            'phone' => $payload['phone'] ?? null,
            'message' => $payload['message'] ?? null,
        ]);

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $dispatch['status'],
            'message_id' => $dispatch['message_id'],
            'target_phone' => $dispatch['target_phone'],
        ];
    }
}
