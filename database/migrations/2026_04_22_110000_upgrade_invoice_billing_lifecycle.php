<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestamp('issued_at')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('issued_at');
            $table->timestamp('overdue_marked_at')->nullable()->after('paid_at');
            $table->timestamp('status_changed_at')->nullable()->after('overdue_marked_at');
            $table->timestamp('whatsapp_sent_at')->nullable()->after('status_changed_at');
            $table->string('whatsapp_recipient', 30)->nullable()->after('whatsapp_sent_at');
            $table->softDeletes();

            $table->index(['customer_id', 'deleted_at']);
            $table->index(['service_id', 'deleted_at']);
        });

        DB::table('invoices')
            ->where('payment_status', 'partial')
            ->update([
                'payment_status' => 'partially_paid',
            ]);

        DB::table('invoices')
            ->whereNull('status_changed_at')
            ->update([
                'status_changed_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);

        DB::table('invoices')
            ->whereNull('issued_at')
            ->whereIn('payment_status', ['issued', 'overdue', 'partially_paid', 'paid'])
            ->update([
                'issued_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);

        DB::table('invoices')
            ->where('payment_status', 'paid')
            ->whereNull('paid_at')
            ->update([
                'paid_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);

        DB::table('invoices')
            ->where('payment_status', 'overdue')
            ->whereNull('overdue_marked_at')
            ->update([
                'overdue_marked_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        DB::table('invoices')
            ->where('payment_status', 'partially_paid')
            ->update([
                'payment_status' => 'partial',
            ]);

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['customer_id', 'deleted_at']);
            $table->dropIndex(['service_id', 'deleted_at']);
            $table->dropColumn([
                'issued_at',
                'paid_at',
                'overdue_marked_at',
                'status_changed_at',
                'whatsapp_sent_at',
                'whatsapp_recipient',
            ]);
            $table->dropSoftDeletes();
        });
    }
};
