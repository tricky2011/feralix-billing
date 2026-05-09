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
        Schema::table('ip_pool_snapshots', function (Blueprint $table): void {
            if (!Schema::hasColumn('ip_pool_snapshots', 'reserved_by_customer_id')) {
                $table->unsignedBigInteger('reserved_by_customer_id')
                    ->nullable()
                    ->after('is_tracked');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ip_pool_snapshots', function (Blueprint $table): void {
            if (Schema::hasColumn('ip_pool_snapshots', 'reserved_by_customer_id')) {
                $table->dropColumn('reserved_by_customer_id');
            }
        });
    }
};
