<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('monitor_pppoe_username', 100)->nullable()->change();
            $table->string('monitor_pppoe_password', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('monitor_pppoe_username', 100)->nullable(false)->change();
            $table->string('monitor_pppoe_password', 100)->nullable(false)->change();
        });
    }
};
