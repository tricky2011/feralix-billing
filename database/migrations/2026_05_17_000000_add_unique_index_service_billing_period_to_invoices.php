<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // Composite unique index: one invoice per (service_id, billing_period)
            // Prevents duplicate invoices even if race condition bypasses application-level check
            $table->unique(['service_id', 'billing_period'], 'invoices_service_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_service_period_unique');
        });
    }
};