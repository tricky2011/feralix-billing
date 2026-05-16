<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_locations', function (Blueprint $table) {
            $table->foreignId('router_id')->nullable()->after('description')->constrained('routers')->nullOnDelete();
            $table->index('router_id');
        });
    }

    public function down(): void
    {
        Schema::table('network_locations', function (Blueprint $table) {
            $table->dropForeign(['router_id']);
            $table->dropColumn('router_id');
        });
    }
};