<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reseller_id')->constrained()->restrictOnDelete();
            $table->foreignId('hotspot_profile_id')->constrained()->restrictOnDelete();
            $table->string('batch_code', 50)->unique();
            $table->unsignedInteger('total_vouchers');
            $table->decimal('total_cost', 15, 2);
            $table->dateTime('generated_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reseller_id', 'generated_at']);
            $table->index(['hotspot_profile_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_batches');
    }
};
