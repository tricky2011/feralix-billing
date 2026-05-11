<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotspot_voucher_id')->constrained('hotspot_vouchers')->cascadeOnDelete();
            $table->foreignId('router_id')->constrained('routers')->cascadeOnDelete();
            $table->string('hotspot_username', 100);
            $table->string('mikrotik_user_id', 50)->nullable()->comment('.id from MikroTik /ip/hotspot/user');
            $table->string('status', 30)->default('provisioning')->comment('provisioning|active|suspended|removed');
            $table->string('sync_error', 255)->nullable();
            $table->timestamps();

            $table->unique(['hotspot_voucher_id', 'router_id'], 'hotspot_services_voucher_router_unique');
            $table->unique(['router_id', 'hotspot_username'], 'hotspot_services_router_username_unique');
            $table->index('router_id');
            $table->index('hotspot_voucher_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_services');
    }
};
