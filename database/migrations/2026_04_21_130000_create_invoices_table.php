<?php

use App\Enums\InvoicePaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->string('invoice_number', 50)->unique();
            $table->string('billing_period', 7);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('payment_status', 30)->default(InvoicePaymentStatus::Unpaid->value);
            $table->timestamps();

            $table->index('billing_period');
            $table->index('invoice_date');
            $table->index('due_date');
            $table->index('payment_status');
            $table->index(['customer_id', 'payment_status']);
            $table->index(['service_id', 'payment_status']);
            $table->unique(['service_id', 'billing_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
