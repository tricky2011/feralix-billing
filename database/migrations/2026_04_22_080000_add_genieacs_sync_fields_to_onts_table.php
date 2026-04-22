<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onts', function (Blueprint $table): void {
            $table->string('optical_info', 255)->nullable()->after('optical_status');
            $table->timestamp('last_inform_at')->nullable()->after('last_seen_at');
            $table->timestamp('genieacs_last_synced_at')->nullable()->after('last_inform_at');

            $table->index('last_inform_at');
            $table->index('genieacs_last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('onts', function (Blueprint $table): void {
            $table->dropIndex(['last_inform_at']);
            $table->dropIndex(['genieacs_last_synced_at']);
            $table->dropColumn([
                'optical_info',
                'last_inform_at',
                'genieacs_last_synced_at',
            ]);
        });
    }
};
