<?php

use App\Enums\HotspotVoucherStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('voucher_batches')->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->restrictOnDelete();
            $table->foreignId('hotspot_profile_id')->constrained()->restrictOnDelete();
            $table->string('username', 100)->unique();
            $table->text('password');
            $table->string('voucher_code', 50)->unique();
            $table->string('locked_mac', 17)->nullable();
            $table->dateTime('first_login_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->unsignedBigInteger('bytes_used')->default(0);
            $table->string('status', 20)->default(HotspotVoucherStatus::Generated->value);
            $table->timestamps();

            $table->index('status');
            $table->index('first_login_at');
            $table->index('expires_at');
            $table->index('locked_mac');
            $table->index(['batch_id', 'status']);
            $table->index(['reseller_id', 'status']);
            $table->index(['hotspot_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_vouchers');
    }
};
