<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained()->nullOnDelete();
            $table->date('expense_date');
            $table->string('category', 100);
            $table->string('description', 150)->nullable();
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('expense_date');
            $table->index('category');
            $table->index(['router_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_expenses');
    }
};
