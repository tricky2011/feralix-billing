<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_locations', function (Blueprint $table): void {
            $table->unsignedBigInteger('router_id')->nullable()->after('id');
            $table->foreign('router_id')->references('id')->on('routers')->nullOnDelete();
        });

        // Backfill: assign router_id from the first OLT linked to each location
        DB::statement('
            UPDATE network_locations nl
            INNER JOIN (
                SELECT network_location_id, MIN(router_id) AS router_id
                FROM olts
                WHERE router_id IS NOT NULL
                GROUP BY network_location_id
            ) o ON o.network_location_id = nl.id
            SET nl.router_id = o.router_id
            WHERE nl.router_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('network_locations', function (Blueprint $table): void {
            $table->dropForeign(['router_id']);
            $table->dropColumn('router_id');
        });
    }
};
