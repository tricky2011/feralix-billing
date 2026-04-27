<?php

namespace App\Providers;

use App\Contracts\Billing\InvoiceWhatsAppGateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Observers\PaymentObserver;
use App\Observers\InvoiceObserver;
use App\Services\Billing\Gateways\StubInvoiceWhatsAppGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InvoiceWhatsAppGateway::class, StubInvoiceWhatsAppGateway::class);
    }

    public function boot(): void
    {
        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);
    }
}
