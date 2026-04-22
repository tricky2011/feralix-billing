<?php

namespace App\Services\Billing;

use App\Models\CashflowTransaction;
use App\Models\Invoice;
use App\Models\Payment;

class CashflowIncomeService
{
    public function recordPayment(Payment $payment, Invoice $invoice): CashflowTransaction
    {
        if ($invoice->service === null || ! array_key_exists('router_id', $invoice->service->getAttributes())) {
            $invoice->unsetRelation('service');
            $invoice->load('service:id,router_id,service_code');
        }

        return CashflowTransaction::query()->updateOrCreate(
            [
                'payment_id' => $payment->id,
            ],
            [
                'invoice_id' => $invoice->id,
                'customer_id' => $payment->customer_id,
                'service_id' => $payment->service_id,
                'router_id' => $invoice->service?->router_id,
                'direction' => 'income',
                'category' => 'invoice_payment',
                'description' => sprintf('Payment for invoice %s', $invoice->invoice_number),
                'amount' => $payment->amount_paid,
                'transacted_at' => $payment->paid_at,
                'created_by' => $payment->created_by,
                'notes' => $payment->notes,
                'metadata' => [
                    'invoice_number' => $invoice->invoice_number,
                    'billing_period' => $invoice->billing_period,
                    'payment_method' => $payment->payment_method,
                    'reference_no' => $payment->reference_no,
                ],
            ],
        );
    }
}
