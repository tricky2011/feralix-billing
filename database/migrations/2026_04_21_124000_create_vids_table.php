<?php

use App\Enums\VidStatus;
use App\Enums\VidType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scope_id')->nullable()->constrained('router_scopes')->nullOnDelete();
            $table->unsignedSmallInteger('vid');
            $table->string('vid_type', 50)->default(VidType::CustomerInternet->value);
            $table->string('subnet_cidr', 50)->nullable();
            $table->string('gateway_ip', 45)->nullable();
            $table->string('pool_start_ip', 45)->nullable();
            $table->string('pool_end_ip', 45)->nullable();
            $table->unsignedSmallInteger('pool_ip_count')->default(5);
            $table->unsignedSmallInteger('rate_limit_mbps')->default(10);
            $table->string('sync_source', 100)->nullable();
            $table->string('status', 20)->default(VidStatus::Available->value);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['router_id', 'vid']);
            $table->index('scope_id');
            $table->index('vid_type');
            $table->index('status');
            $table->index('customer_id');
            $table->index('service_id');
            $table->index(['router_id', 'status']);
            $table->index(['router_id', 'vid_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vids');
    }
};
