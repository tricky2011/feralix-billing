<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropUnique(['monitor_pppoe_username']);
            $table->unique(['router_id', 'monitor_pppoe_username'], 'services_router_monitor_pppoe_unique');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropUnique('services_router_monitor_pppoe_unique');
            $table->unique('monitor_pppoe_username');
        });
    }
};
