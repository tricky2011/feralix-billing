<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->decimal('amount_paid', 15, 2);
            $table->string('payment_method', 50);
            $table->dateTime('paid_at');
            $table->string('reference_no', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('paid_at');
            $table->index('payment_method');
            $table->index('reference_no');
            $table->index(['invoice_id', 'paid_at']);
            $table->index(['customer_id', 'paid_at']);
            $table->index(['service_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
