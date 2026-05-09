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
        Schema::table('routers', function (Blueprint $table) {
            $table->string('ros_version', 10)->nullable()->default(null)->after('api_password')
                ->comment('RouterOS major version: 6 or 7');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->string('ros_version', 10)->nullable()->default(null)->after('api_password')
                ->comment('RouterOS major version: 6 or 7');
        });
    }
};
