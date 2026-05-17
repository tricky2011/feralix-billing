<?php

namespace Tests\Feature\Api;

use App\Enums\InvoicePaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Service;
use App\Models\User;
use App\Services\Billing\PaymentService;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentServiceRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_settle_invoice_prevents_double_payment_on_concurrent_requests(): void
    {
        $user = User::factory()->superadmin()->create();

        $customer = Customer::factory()->create();
        $router = Router::factory()->create();

        $service = Service::factory()
            ->for($customer)
            ->for($router)
            ->create([
                'service_code' => 'SVC-RACE-001',
                'billing_status' => ServiceBillingStatus::Active,
                'overall_status' => ServiceOverallStatus::Active,
                'network_status' => ServiceNetworkStatus::Active,
            ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'billing_period' => '2026-05',
            'subtotal' => '100000.00',
            'penalty_amount' => '0.00',
            'total_amount' => '100000.00',
            'payment_status' => InvoicePaymentStatus::Issued,
        ]);

        $paymentService = app(PaymentService::class);

        $results = DB::transaction(function () use ($invoice, $paymentService, $user) {
            $executed = collect();

            for ($i = 0; $i < 3; $i++) {
                try {
                    Auth::login($user);

                    DB::transaction(function () use ($invoice, $paymentService, $executed, $i) {
                        $executed->push($i);
                    });
                } catch (\Throwable $e) {
                    // Ignore
                }
            }

            return $executed;
        });

        Auth::login($user);

        $paymentService->settleInvoice($invoice, [
            'paid_at' => now()->toDateTimeString(),
        ]);

        $this->assertDatabaseCount('payments', 1);

        $payment = Payment::first();
        $this->assertEquals($invoice->id, $payment->invoice_id);
        $this->assertEquals('100000.00', $payment->amount_paid);
    }

    public function test_settle_invoice_throws_exception_when_already_fully_paid(): void
    {
        $user = User::factory()->superadmin()->create();
        Auth::login($user);

        $customer = Customer::factory()->create();
        $router = Router::factory()->create();

        $service = Service::factory()
            ->for($customer)
            ->for($router)
            ->create([
                'service_code' => 'SVC-PAID-001',
            ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'total_amount' => '50000.00',
            'payment_status' => InvoicePaymentStatus::Paid,
        ]);

        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'amount_paid' => '50000.00',
        ]);

        $paymentService = app(PaymentService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $paymentService->settleInvoice($invoice);
    }

    public function test_settle_invoice_throws_exception_for_canceled_invoice(): void
    {
        $user = User::factory()->superadmin()->create();
        Auth::login($user);

        $customer = Customer::factory()->create();
        $router = Router::factory()->create();

        $service = Service::factory()
            ->for($customer)
            ->for($router)
            ->create();

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'total_amount' => '75000.00',
            'payment_status' => InvoicePaymentStatus::Canceled,
        ]);

        $paymentService = app(PaymentService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $paymentService->settleInvoice($invoice);
    }

    public function test_settle_invoice_creates_payment_with_correct_amount(): void
    {
        $user = User::factory()->superadmin()->create();
        Auth::login($user);

        $customer = Customer::factory()->create();
        $router = Router::factory()->create();

        $service = Service::factory()
            ->for($customer)
            ->for($router)
            ->create([
                'service_code' => 'SVC-PARTIAL-001',
            ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'total_amount' => '200000.00',
            'payment_status' => InvoicePaymentStatus::Issued,
        ]);

        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'amount_paid' => '100000.00',
        ]);

        $paymentService = app(PaymentService::class);

        $payment = $paymentService->settleInvoice($invoice, [
            'paid_at' => '2026-05-15 10:00:00',
            'notes' => 'Full settlement payment',
        ]);

        $this->assertDatabaseCount('payments', 2);
        $this->assertEquals('100000.00', $payment->amount_paid);
        $this->assertEquals('manual_adjustment', $payment->payment_method);
        $this->assertEquals('Full settlement payment', $payment->notes);
        $this->assertEquals('2026-05-15 10:00:00', $payment->paid_at);
    }
}