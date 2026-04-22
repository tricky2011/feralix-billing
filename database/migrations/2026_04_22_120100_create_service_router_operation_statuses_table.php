<?php

use App\Enums\ServiceAccessMode;
use App\Enums\ServiceIsolationTargetType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_router_operation_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('access_mode', 20)->default(ServiceAccessMode::Vlan->value);
            $table->string('isolation_target_type', 20)->default(ServiceIsolationTargetType::Subnet->value);
            $table->string('pppoe_username', 100)->nullable();
            $table->boolean('ppp_secret_found')->default(false);
            $table->string('ppp_secret_profile', 100)->nullable();
            $table->boolean('ppp_active_found')->default(false);
            $table->string('ppp_active_address', 45)->nullable();
            $table->string('ppp_active_caller_id', 50)->nullable();
            $table->string('static_ip_address', 45)->nullable();
            $table->string('static_mac_address', 50)->nullable();
            $table->boolean('arp_found')->default(false);
            $table->string('queue_name', 100)->nullable();
            $table->string('queue_target', 255)->nullable();
            $table->boolean('queue_found')->default(false);
            $table->string('address_list_name', 100)->nullable();
            $table->string('address_list_target', 100)->nullable();
            $table->boolean('address_list_found')->default(false);
            $table->foreignId('open_isolation_id')->nullable()->constrained('service_isolations')->nullOnDelete();
            $table->boolean('isolation_applied')->default(false);
            $table->string('isolation_detected_via', 50)->nullable();
            $table->string('legacy_source', 50)->nullable();
            $table->json('remote_snapshot')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('isolation_verified_at')->nullable();
            $table->timestamps();

            $table->unique('service_id');
            $table->index('router_id');
            $table->index(['router_id', 'access_mode']);
            $table->index(['router_id', 'isolation_target_type']);
            $table->index('pppoe_username');
            $table->index('static_ip_address');
            $table->index('open_isolation_id');
            $table->index('last_synced_at');
            $table->index('isolation_verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_router_operation_statuses');
    }
};
