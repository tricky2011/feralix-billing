<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if column already exists (from previous migration)
        if (! Schema::hasColumn('hotspot_vouchers', 'password_plain')) {
            Schema::table('hotspot_vouchers', function (Blueprint $table): void {
                // password_plain: plaintext password untuk RADIUS auth query (bukan encrypted)
                // Disimpan bersamaan dengan password encrypted yang sudah ada
                // Menggunakan varchar(255) agar bisa di-index untuk query RADIUS
                $table->string('password_plain', 255)->nullable()->after('password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hotspot_vouchers', 'password_plain')) {
            Schema::table('hotspot_vouchers', function (Blueprint $table): void {
                $table->dropColumn('password_plain');
            });
        }
    }
};
