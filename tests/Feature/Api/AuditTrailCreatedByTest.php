<?php

namespace Tests\Feature\Api;

use App\Enums\InvoicePaymentStatus;
use App\Enums\ServiceIsolationType;
use App\Models\Customer;
use App\Models\HotspotProfile;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceIsolation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailCreatedByTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_created_by_cannot_be_set_from_request_body(): void
    {
        $actingUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $customer = Customer::factory()->create();
        $package = Package::factory()->create();
        $router = Router::factory()->create();
        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'billing_status' => 'unpaid',
        ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'total_amount' => 100000,
            'payment_status' => InvoicePaymentStatus::Unpaid,
        ]);

        $response = $this->actingAs($actingUser, 'sanctum')->postJson('/api/v1/admin/payments', [
            'invoice_id' => $invoice->id,
            'amount_paid' => 50000,
            'payment_method' => 'transfer',
            'paid_at' => now()->toDateTimeString(),
            'created_by' => $otherUser->id,
        ]);

        $response->assertCreated();

        $payment = Payment::latest('id')->first();
        $this->assertEquals($actingUser->id, $payment->created_by);
        $this->assertNotEquals($otherUser->id, $payment->created_by);
    }

    public function test_bulk_invoice_mark_paid_respects_audit_trail(): void
    {
        $actingUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $customer = Customer::factory()->create();
        $package = Package::factory()->create();
        $router = Router::factory()->create();
        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
        ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'total_amount' => 100000,
            'payment_status' => InvoicePaymentStatus::Unpaid,
        ]);

        $response = $this->actingAs($actingUser, 'sanctum')->postJson('/api/v1/admin/invoices/bulk-action', [
            'invoice_ids' => [$invoice->id],
            'action' => 'mark_paid',
            'payment_method' => 'cash',
            'paid_at' => now()->toDateTimeString(),
            'created_by' => $otherUser->id,
        ]);

        $response->assertOk();

        $payment = Payment::latest('id')->first();
        $this->assertEquals($actingUser->id, $payment->created_by);
        $this->assertNotEquals($otherUser->id, $payment->created_by);
    }

    public function test_service_isolation_created_by_cannot_be_manipulated(): void
    {
        $actingUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $customer = Customer::factory()->create();
        $package = Package::factory()->create();
        $router = Router::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
        ]);

        $response = $this->actingAs($actingUser, 'sanctum')->postJson('/api/v1/admin/service-isolations', [
            'service_id' => $service->id,
            'isolation_type' => 'manual',
            'created_by' => $otherUser->id,
            'notes' => 'Test isolation',
        ]);

        $response->assertCreated();

        $isolation = ServiceIsolation::latest('id')->first();
        $this->assertEquals($actingUser->id, $isolation->created_by);
        $this->assertNotEquals($otherUser->id, $isolation->created_by);
    }

    public function test_voucher_batch_created_by_cannot_be_manipulated(): void
    {
        $actingUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $reseller = Reseller::factory()->create([
            'balance' => 100000,
        ]);

        $profile = HotspotProfile::factory()->create([
            'price' => 10000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($actingUser, 'sanctum')->postJson('/api/v1/admin/voucher-batches', [
            'reseller_id' => $reseller->id,
            'hotspot_profile_id' => $profile->id,
            'total_vouchers' => 5,
            'created_by' => $otherUser->id,
        ]);

        $response->assertCreated();

        $batch = \App\Models\VoucherBatch::latest('id')->first();
        $this->assertEquals($actingUser->id, $batch->created_by);
        $this->assertNotEquals($otherUser->id, $batch->created_by);
    }
}