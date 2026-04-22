<?php

use App\Enums\MonitorPppoeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_monitor_pppoe_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('monitor_pppoe_username', 100);
            $table->string('status', 20)->default(MonitorPppoeStatus::Offline->value);
            $table->string('base_overall_status', 30)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_status_changed_at')->nullable();
            $table->json('session_info')->nullable();
            $table->timestamps();

            $table->unique('service_id');
            $table->index('router_id');
            $table->index('monitor_pppoe_username');
            $table->index('status');
            $table->index(['router_id', 'status']);
            $table->index('last_seen_at');
            $table->index('last_synced_at');
        });

        Schema::create('service_monitor_pppoe_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('monitor_pppoe_username', 100);
            $table->string('previous_status', 20)->nullable();
            $table->string('status', 20);
            $table->timestamp('last_seen_at')->nullable();
            $table->json('session_info')->nullable();
            $table->timestamp('observed_at')->useCurrent();

            $table->index('service_id');
            $table->index('router_id');
            $table->index('monitor_pppoe_username');
            $table->index('status');
            $table->index(['service_id', 'observed_at']);
            $table->index(['router_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_monitor_pppoe_logs');
        Schema::dropIfExists('service_monitor_pppoe_statuses');
    }
};
