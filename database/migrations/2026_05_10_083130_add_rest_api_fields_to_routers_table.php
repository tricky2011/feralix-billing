<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('rest_port')->default(80)->after('api_port');
            $table->boolean('use_rest_api')->default(false)->after('rest_port');
            $table->boolean('rest_tls')->default(false)->after('use_rest_api');
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->dropColumn(['rest_port', 'use_rest_api', 'rest_tls']);
        });
    }
};