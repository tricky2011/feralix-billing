<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ip_pool_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('pool_name', 100);
            $table->unsignedSmallInteger('vlan_id')->nullable()->index();
            $table->string('vlan_name', 100)->nullable();
            $table->string('interface', 100)->nullable();
            $table->string('dhcp_server_name', 100)->nullable();
            $table->string('primary_range', 255)->nullable();
            $table->unsignedSmallInteger('total_ips')->default(0);
            $table->unsignedSmallInteger('used_ips')->default(0);
            $table->unsignedSmallInteger('free_ips')->default(0);
            $table->decimal('usage_percentage', 5, 2)->default(0);
            $table->boolean('is_full')->default(false);
            $table->boolean('is_available')->default(false);
            $table->boolean('is_reserved')->default(false);
            $table->string('availability_status', 20)->default('unknown');
            $table->boolean('is_tracked')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['router_id', 'pool_name']);
            $table->index(['router_id', 'vlan_id']);
            $table->index(['router_id', 'availability_status']);
            $table->index(['router_id', 'is_tracked']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_pool_snapshots');
    }
};
