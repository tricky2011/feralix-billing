<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            // Drop global unique constraint on monitor_pppoe_username
            $table->dropUnique('services_monitor_pppoe_username_unique');

            // Add composite unique constraint: same username allowed on different routers
            $table->unique(['router_id', 'monitor_pppoe_username'], 'services_router_monitor_pppoe_unique');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            // Revert: drop composite unique
            $table->dropUnique('services_router_monitor_pppoe_unique');

            // Restore global unique constraint
            $table->string('monitor_pppoe_username', 100)->unique()->change();
        });
    }
};