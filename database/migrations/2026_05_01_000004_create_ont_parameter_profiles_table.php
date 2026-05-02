<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ont_parameter_profiles', function (Blueprint $table): void {
            $table->id();

            // Brand identifier (Huawei, ZTE, FiberHome, dll)
            $table->string('brand', 50);
            $table->index('brand');

            // Optional regex pattern to match model
            $table->string('model_pattern', 100)->nullable();

            // TR-069 parameter paths
            $table->string('param_ssid_2g', 200)->nullable()->comment('Path untuk SSID 2.4GHz');
            $table->string('param_ssid_5g', 200)->nullable()->comment('Path untuk SSID 5GHz');
            $table->string('param_pass_2g', 200)->nullable()->comment('Path untuk password WiFi 2.4GHz');
            $table->string('param_pass_5g', 200)->nullable()->comment('Path untuk password WiFi 5GHz');
            $table->string('param_rx_power', 200)->nullable()->comment('Path untuk rx power');
            $table->string('param_tx_power', 200)->nullable()->comment('Path untuk tx power');
            $table->string('param_uptime', 200)->nullable()->comment('Path untuk uptime ONT');

            // Additional fields for monitoring
            $table->string('param_online_status', 200)->nullable()->comment('Path untuk online status');
            $table->string('param_ip_address', 200)->nullable()->comment('Path untuk IP address');

            // Notes
            $table->text('notes')->nullable();

            // Active status
            $table->boolean('is_active')->default(true);
            $table->index('is_active');

            $table->timestamps();

            // Unique constraint: one profile per brand (if no model_pattern)
            $table->unique(['brand']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ont_parameter_profiles');
    }
};
