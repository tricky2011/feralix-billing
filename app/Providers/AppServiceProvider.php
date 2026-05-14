<?php

namespace App\Providers;

use App\Contracts\Billing\InvoiceWhatsAppGateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Observers\PaymentObserver;
use App\Observers\InvoiceObserver;
use App\Services\Billing\Gateways\StubInvoiceWhatsAppGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('bulk-operations', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('destructive', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('router-sync', function (Request $request) {
            $routerId = $request->input('router_id') ?? $request->route('router') ?? 'all';
            $key = $request->user()?->id . ':' . $routerId;
            return Limit::perMinute(2)->by($key);
        });

        RateLimiter::for('auto-suspend', function (Request $request) {
            return Limit::perMinutes(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
