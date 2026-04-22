<?php

use App\Enums\MikrotikSyncJobStatus;
use App\Enums\MikrotikSyncJobType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mikrotik_sync_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('job_type', 50)->default(MikrotikSyncJobType::VidInventory->value);
            $table->string('job_status', 30)->default(MikrotikSyncJobStatus::Pending->value);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('total_found')->default(0);
            $table->unsignedInteger('total_new')->default(0);
            $table->unsignedInteger('total_updated')->default(0);
            $table->unsignedInteger('total_conflict')->default(0);
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index('router_id');
            $table->index('job_type');
            $table->index('job_status');
            $table->index(['router_id', 'job_status']);
            $table->index('started_at');
            $table->index('finished_at');
        });

        Schema::create('mikrotik_sync_vid_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_job_id')->constrained('mikrotik_sync_jobs')->cascadeOnDelete();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('vid');
            $table->string('action_type', 30);
            $table->string('remote_vlan_name', 150)->nullable();
            $table->string('remote_subnet_cidr', 50)->nullable();
            $table->string('remote_pool_range', 100)->nullable();
            $table->string('local_status_before', 20)->nullable();
            $table->string('local_status_after', 20)->nullable();
            $table->boolean('conflict_flag')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('sync_job_id');
            $table->index('router_id');
            $table->index('vid');
            $table->index('action_type');
            $table->index('conflict_flag');
            $table->index(['router_id', 'vid']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikrotik_sync_vid_logs');
        Schema::dropIfExists('mikrotik_sync_jobs');
    }
};
