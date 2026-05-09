<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('subnet_cidr', 50)->nullable()->change();
            $table->string('dhcp_pool_start', 45)->nullable()->change();
            $table->string('dhcp_pool_end', 45)->nullable()->change();
            $table->unsignedSmallInteger('internet_vid')->nullable()->change();
            $table->foreignId('router_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('subnet_cidr', 50)->nullable(false)->change();
            $table->string('dhcp_pool_start', 45)->nullable(false)->change();
            $table->string('dhcp_pool_end', 45)->nullable(false)->change();
            $table->unsignedSmallInteger('internet_vid')->nullable(false)->change();
            $table->foreignId('router_id')->nullable(false)->change();
        });
    }
};
