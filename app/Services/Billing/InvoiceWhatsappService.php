<?php

namespace App\Services\Billing;

use App\Contracts\Billing\InvoiceWhatsAppGateway;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceWhatsappService
{
    public function __construct(private readonly InvoiceWhatsAppGateway $gateway) {}

    public function send(Invoice $invoice, array $payload = []): array
    {
        return DB::transaction(function () use ($invoice, $payload): array {
            $lockedInvoice = Invoice::query()
                ->with([
                    'customer:id,full_name,phone',
                    'service:id,service_code',
                ])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $targetPhone = $this->resolveTargetPhone(
                $payload['phone'] ?? null,
                $lockedInvoice->customer?->phone,
            );
            $message = $this->resolveMessage($lockedInvoice, $payload['message'] ?? null);
            $sentAt = Carbon::now();
            $dispatch = $this->gateway->send([
                'invoice_id' => $lockedInvoice->id,
                'invoice_number' => $lockedInvoice->invoice_number,
                'service_code' => $lockedInvoice->service?->service_code,
                'customer_name' => $lockedInvoice->customer?->full_name,
                'phone' => $targetPhone,
                'message' => $message,
                'total_amount' => $lockedInvoice->total_amount,
                'due_date' => $lockedInvoice->due_date?->toDateString(),
                'billing_period' => $lockedInvoice->billing_period,
            ]);

            $lockedInvoice->update([
                'whatsapp_sent_at' => $sentAt,
                'whatsapp_recipient' => $targetPhone,
            ]);

            return [
                ...$dispatch,
                'target_phone' => $targetPhone,
                'message' => $message,
                'sent_at' => $sentAt->toISOString(),
                'invoice_id' => $lockedInvoice->id,
            ];
        });
    }

    private function resolveTargetPhone(?string $requestPhone, ?string $customerPhone): string
    {
        $phone = trim((string) ($requestPhone ?: $customerPhone));

        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => 'A WhatsApp destination number is required.',
            ]);
        }

        return $phone;
    }

    private function resolveMessage(Invoice $invoice, ?string $customMessage): string
    {
        $customMessage = trim((string) $customMessage);

        if ($customMessage !== '') {
            return $customMessage;
        }

        return trim(sprintf(
            'Invoice %s untuk %s periode %s sebesar %s jatuh tempo %s.',
            $invoice->invoice_number,
            $invoice->customer?->full_name ?? 'pelanggan',
            $invoice->billing_period,
            $invoice->total_amount,
            $invoice->due_date?->toDateString() ?? '-',
        ));
    }
}
