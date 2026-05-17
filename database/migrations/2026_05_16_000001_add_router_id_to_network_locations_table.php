<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_locations', function (Blueprint $table) {
            $table->foreignId('router_id')->nullable()->after('id')->constrained('routers')->nullOnDelete();
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