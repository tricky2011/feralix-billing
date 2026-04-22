<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('router_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 20);
            $table->string('category', 50);
            $table->string('description', 150)->nullable();
            $table->decimal('amount', 15, 2);
            $table->dateTime('transacted_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('direction');
            $table->index('category');
            $table->index('transacted_at');
            $table->index(['invoice_id', 'transacted_at']);
            $table->index(['service_id', 'transacted_at']);
            $table->index(['router_id', 'transacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_transactions');
    }
};
