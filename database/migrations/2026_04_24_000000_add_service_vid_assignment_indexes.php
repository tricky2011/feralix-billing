<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->index(['vid_id', 'deleted_at', 'overall_status'], 'services_vid_deleted_overall_idx');
            $table->index(['router_id', 'internet_vid', 'deleted_at', 'overall_status'], 'services_router_vid_deleted_overall_idx');
        });

        Schema::table('vids', function (Blueprint $table): void {
            $table->index(['status', 'service_id'], 'vids_status_service_idx');
            $table->index(['service_id', 'customer_id'], 'vids_service_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vids', function (Blueprint $table): void {
            $table->dropIndex('vids_service_customer_idx');
            $table->dropIndex('vids_status_service_idx');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('services_router_vid_deleted_overall_idx');
            $table->dropIndex('services_vid_deleted_overall_idx');
        });
    }
};
