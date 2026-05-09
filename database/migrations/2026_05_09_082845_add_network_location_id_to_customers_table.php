<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('network_location_id')
                ->nullable()
                ->after('location_id')
                ->constrained('network_locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeignIdFor(\App\Models\NetworkLocation::class);
            $table->dropColumn('network_location_id');
        });
    }
};
