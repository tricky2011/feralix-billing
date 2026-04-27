<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflows', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->decimal('amount', 15, 2);
            $table->foreignId('category_id')->nullable()->constrained('cashflow_categories')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->string('source', 20);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('category_id');
            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
            $table->index('source');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflows');
    }
};
