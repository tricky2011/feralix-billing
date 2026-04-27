<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\Finance\CashflowService;

class PaymentObserver
{
    public function __construct(private readonly CashflowService $cashflowService) {}

    public function created(Payment $payment): void
    {
        $this->cashflowService->createFromPayment($payment);
    }
}
