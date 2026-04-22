<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 30)
                ->default(UserRole::Superadmin->value)
                ->after('password');
            $table->foreignId('technician_id')
                ->nullable()
                ->after('role')
                ->constrained('technicians')
                ->nullOnDelete();
            $table->foreignId('dashboard_active_router_id')
                ->nullable()
                ->after('technician_id')
                ->constrained('routers')
                ->nullOnDelete();

            $table->index('role');
            $table->index('technician_id');
            $table->index('dashboard_active_router_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['dashboard_active_router_id']);
            $table->dropIndex(['technician_id']);
            $table->dropIndex(['role']);
            $table->dropConstrainedForeignId('dashboard_active_router_id');
            $table->dropConstrainedForeignId('technician_id');
            $table->dropColumn('role');
        });
    }
};
