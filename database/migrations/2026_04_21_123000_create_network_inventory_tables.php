<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routers', function (Blueprint $table): void {
            $table->id();
            $table->string('router_code', 30)->unique();
            $table->string('router_name', 150);
            $table->string('router_role', 50);
            $table->string('mgmt_ip', 45);
            $table->unsignedInteger('api_port');
            $table->string('api_username', 100);
            $table->string('api_password', 255);
            $table->string('location_name', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('router_role');
            $table->index('is_active');
            $table->index(['router_name', 'location_name']);
        });

        Schema::create('router_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('scope_name', 100);
            $table->unsignedSmallInteger('monitor_vid');
            $table->unsignedSmallInteger('vid_start');
            $table->unsignedSmallInteger('vid_end');
            $table->boolean('is_special')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('router_id');
            $table->index('monitor_vid');
            $table->index('is_special');
            $table->index(['router_id', 'vid_start', 'vid_end']);
        });

        Schema::create('olts', function (Blueprint $table): void {
            $table->id();
            $table->string('olt_code', 30)->unique();
            $table->string('olt_name', 150);
            $table->string('mgmt_ip', 45)->nullable();
            $table->string('vendor_name', 100)->nullable();
            $table->string('location_name', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('vendor_name');
            $table->index('is_active');
            $table->index(['olt_name', 'location_name']);
        });

        Schema::create('onts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('olt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ont_sn', 50)->unique();
            $table->string('ont_name', 150)->nullable();
            $table->string('pon_port', 50)->nullable();
            $table->unsignedInteger('onu_id')->nullable();
            $table->string('ssid_name', 100)->nullable();
            $table->string('wifi_password', 255)->nullable();
            $table->string('optical_status', 50)->nullable();
            $table->decimal('rx_power', 8, 2)->nullable();
            $table->decimal('tx_power', 8, 2)->nullable();
            $table->string('genieacs_device_id', 100)->nullable();
            $table->string('status', 30);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('olt_id');
            $table->index('status');
            $table->index('optical_status');
            $table->index(['pon_port', 'onu_id']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onts');
        Schema::dropIfExists('olts');
        Schema::dropIfExists('router_scopes');
        Schema::dropIfExists('routers');
    }
};
