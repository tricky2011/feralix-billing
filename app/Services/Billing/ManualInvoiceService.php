<?php

namespace App\Services\Billing;

use App\Enums\InvoicePaymentStatus;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualInvoiceService
{
    public function __construct(
        private readonly InvoicePaymentStatusService $invoicePaymentStatusService,
        private readonly ServiceBillingStatusService $serviceBillingStatusService,
        private readonly RoleRouterScopeService $roleRouterScopeService,
    ) {}

    public function create(array $payload): Invoice
    {
        return DB::transaction(function () use ($payload): Invoice {
            $service = $this->resolveService((int) $payload['service_id']);
            $this->roleRouterScopeService->ensureModelAccessibleForInput($service, 'service_id');
            $this->ensureServiceBelongsToCustomer($service, (int) $payload['customer_id']);

            $invoice = Invoice::query()->create(
                $this->payloadForWrite(
                    service: $service,
                    payload: $payload,
                ),
            );

            if ($service->exists) {
                $this->serviceBillingStatusService->sync($service);
            }

            return $invoice->refresh();
        });
    }

    public function update(Invoice $invoice, array $payload): Invoice
    {
        return DB::transaction(function () use ($invoice, $payload): Invoice {
            $lockedInvoice = Invoice::query()
                ->with(['service', 'payments', 'serviceIsolations'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $this->ensureMutable($lockedInvoice);

            $service = $this->resolveService((int) $payload['service_id']);
            $this->roleRouterScopeService->ensureModelAccessibleForInput($service, 'service_id');
            $this->ensureServiceBelongsToCustomer($service, (int) $payload['customer_id']);

            $lockedInvoice->update(
                $this->payloadForWrite(
                    service: $service,
                    payload: $payload,
                    existingInvoice: $lockedInvoice,
                ),
            );

            if ($lockedInvoice->service !== null && (int) $lockedInvoice->service->id !== (int) $service->id) {
                $this->serviceBillingStatusService->sync($lockedInvoice->service);
            }

            $this->serviceBillingStatusService->sync($service);

            return $lockedInvoice->refresh();
        });
    }

    public function ensureMutable(Invoice $invoice): void
    {
        if ($invoice->payments()->exists()) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoices with recorded payments can no longer be edited.',
            ]);
        }

        if ($invoice->serviceIsolations()->exists()) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoices with isolation history can no longer be edited.',
            ]);
        }
    }

    public function generateInvoiceNumber(Service $service, string $billingPeriod): string
    {
        $period = str_replace('-', '', $billingPeriod);

        return sprintf('INV-%s-%06d', $period, $service->id);
    }

    private function payloadForWrite(Service $service, array $payload, ?Invoice $existingInvoice = null): array
    {
        $invoiceDate = Carbon::parse($payload['invoice_date'])->startOfDay();
        $dueDate = Carbon::parse($payload['due_date'])->startOfDay();
        $subtotal = $this->normalizeMoney($payload['subtotal']);
        $penaltyAmount = $this->normalizeMoney($payload['penalty_amount'] ?? 0);
        $totalAmount = $this->calculateTotalAmount($subtotal, $penaltyAmount);
        $issueNow = array_key_exists('issue_now', $payload)
            ? (bool) $payload['issue_now']
            : ($existingInvoice === null ? false : $existingInvoice->issued_at !== null);
        $referenceDate = now();
        $status = $this->invoicePaymentStatusService->resolve(
            amountPaid: 0,
            totalAmount: (float) $totalAmount,
            dueDate: $dueDate->copy(),
            issued: $issueNow,
            referenceDate: $referenceDate->copy()->startOfDay(),
        );

        return [
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'invoice_number' => $payload['invoice_number'] ?? $existingInvoice?->invoice_number ?? $this->generateInvoiceNumber($service, $payload['billing_period']),
            'billing_period' => $payload['billing_period'],
            'invoice_date' => $invoiceDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subtotal' => $subtotal,
            'penalty_amount' => $penaltyAmount,
            'total_amount' => $totalAmount,
            'payment_status' => $status->value,
            'issued_at' => $status === InvoicePaymentStatus::Unpaid ? null : ($existingInvoice?->issued_at ?? $invoiceDate),
            'paid_at' => $status === InvoicePaymentStatus::Paid ? ($existingInvoice?->paid_at ?? $referenceDate) : null,
            'overdue_marked_at' => $status === InvoicePaymentStatus::Overdue ? ($existingInvoice?->overdue_marked_at ?? $referenceDate) : null,
            'status_changed_at' => $referenceDate,
            'whatsapp_sent_at' => $existingInvoice?->whatsapp_sent_at,
            'whatsapp_recipient' => $existingInvoice?->whatsapp_recipient,
        ];
    }

    private function resolveService(int $serviceId): Service
    {
        return Service::query()
            ->with(['customer:id,customer_code,full_name', 'package:id,package_name,monthly_price'])
            ->findOrFail($serviceId);
    }

    private function ensureServiceBelongsToCustomer(Service $service, int $customerId): void
    {
        if ((int) $service->customer_id === $customerId) {
            return;
        }

        throw ValidationException::withMessages([
            'customer_id' => 'The selected customer does not match the selected service.',
        ]);
    }

    private function calculateTotalAmount(string|int|float $subtotal, string|int|float $penaltyAmount): string
    {
        return number_format(
            round((float) $subtotal, 2) + round((float) $penaltyAmount, 2),
            2,
            '.',
            '',
        );
    }

    private function normalizeMoney(string|int|float $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }
}
