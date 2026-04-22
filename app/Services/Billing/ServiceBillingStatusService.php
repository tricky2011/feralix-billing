<?php

namespace App\Services\Billing;

use App\Enums\InvoicePaymentStatus;
use App\Enums\ServiceBillingStatus;
use App\Models\Invoice;
use App\Models\Service;
use Illuminate\Support\Carbon;

class ServiceBillingStatusService
{
    public function sync(Service $service, ?Carbon $referenceDate = null): ServiceBillingStatus
    {
        $status = $this->resolve($service, $referenceDate);
        $currentStatus = $this->currentStatus($service);

        if ($currentStatus !== $status) {
            $service->update([
                'billing_status' => $status->value,
            ]);
        }

        return $status;
    }

    public function resolve(Service $service, ?Carbon $referenceDate = null): ServiceBillingStatus
    {
        $referenceDate ??= now()->startOfDay();
        $invoiceQuery = Invoice::query()
            ->where('service_id', $service->id);

        if ((clone $invoiceQuery)->overdue($referenceDate)->exists()) {
            return ServiceBillingStatus::Overdue;
        }

        if ((clone $invoiceQuery)->whereIn('payment_status', [
            InvoicePaymentStatus::Unpaid->value,
            InvoicePaymentStatus::Issued->value,
            InvoicePaymentStatus::PartiallyPaid->value,
        ])->exists()) {
            return ServiceBillingStatus::Pending;
        }

        if ((clone $invoiceQuery)->where('payment_status', InvoicePaymentStatus::Paid->value)->exists()) {
            return ServiceBillingStatus::Paid;
        }

        return $this->currentStatus($service);
    }

    private function currentStatus(Service $service): ServiceBillingStatus
    {
        if ($service->billing_status instanceof ServiceBillingStatus) {
            return $service->billing_status;
        }

        return ServiceBillingStatus::tryFrom((string) $service->billing_status)
            ?? ServiceBillingStatus::Pending;
    }
}
