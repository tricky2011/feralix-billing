<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('location_id')
                ->nullable()
                ->after('address')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('preferred_olt_id')
                ->nullable()
                ->after('location_id')
                ->constrained('olts')
                ->nullOnDelete();
            $table->foreignId('assigned_technician_id')
                ->nullable()
                ->after('preferred_olt_id')
                ->constrained('technicians')
                ->nullOnDelete();

            $table->index('location_id');
            $table->index('preferred_olt_id');
            $table->index('assigned_technician_id');
        });

        Schema::table('olts', function (Blueprint $table): void {
            $table->foreignId('location_id')
                ->nullable()
                ->after('vendor_name')
                ->constrained()
                ->nullOnDelete();

            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::table('olts', function (Blueprint $table): void {
            $table->dropIndex(['location_id']);
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['assigned_technician_id']);
            $table->dropIndex(['preferred_olt_id']);
            $table->dropIndex(['location_id']);
            $table->dropConstrainedForeignId('assigned_technician_id');
            $table->dropConstrainedForeignId('preferred_olt_id');
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
