<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_pools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('pool_name');
            $table->unsignedInteger('vlan_id')->nullable();
            $table->string('vlan_name')->nullable();
            $table->string('interface')->nullable();
            $table->string('dhcp_server_name')->nullable();
            $table->text('ranges')->nullable();
            $table->unsignedInteger('total_ips')->default(0);
            $table->unsignedInteger('used_ips')->default(0);
            $table->unsignedInteger('free_ips')->default(0);
            $table->decimal('usage_percentage', 5, 2)->default(0);
            $table->boolean('is_full')->default(false);
            $table->boolean('is_available')->default(false);
            $table->boolean('is_reserved')->default(false);
            $table->string('availability_status')->default('unknown');
            $table->boolean('is_tracked')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['router_id', 'pool_name']);
            $table->index(['router_id', 'is_tracked']);
            $table->index(['vlan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_pools');
    }
};
