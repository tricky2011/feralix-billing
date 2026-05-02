<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            // ip_count: jumlah IP aktif (1-5) per customer
            $table->unsignedSmallInteger('ip_count')->default(5)->after('rate_limit_mbps');
            $table->index('ip_count', 'idx_services_ip_count');

            // monthly_price: harga flat per VID per bulan (hasil negosiasi)
            $table->decimal('monthly_price', 12, 2)->nullable()->after('ip_count');
            $table->index('monthly_price', 'idx_services_monthly_price');

            // billing_day: tanggal tagihan (1-28), nullable untuk handle edge case (31->1)
            $table->unsignedTinyInteger('billing_day')->nullable()->after('monthly_price');
            $table->index('billing_day', 'idx_services_billing_day');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('idx_services_ip_count');
            $table->dropIndex('idx_services_monthly_price');
            $table->dropIndex('idx_services_billing_day');

            $table->dropColumn(['ip_count', 'monthly_price', 'billing_day']);
        });
    }
};
