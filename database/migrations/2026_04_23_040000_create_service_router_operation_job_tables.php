<?php

use App\Enums\ServiceRouterOperationJobStatus;
use App\Enums\ServiceRouterOperationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_router_operation_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_isolation_id')->nullable()->constrained('service_isolations')->nullOnDelete();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('operation_type', 20)->default(ServiceRouterOperationType::Isolate->value);
            $table->string('job_status', 30)->default(ServiceRouterOperationJobStatus::Pending->value);
            $table->string('queue_name', 100)->nullable();
            $table->string('address_list_name', 100)->nullable();
            $table->string('target_address', 45)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index('service_id');
            $table->index('service_isolation_id');
            $table->index('router_id');
            $table->index('operation_type');
            $table->index('job_status');
            $table->index(['router_id', 'job_status']);
            $table->index(['service_id', 'operation_type']);
            $table->index('started_at');
            $table->index('finished_at');
        });

        Schema::create('service_router_operation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operation_job_id')->constrained('service_router_operation_jobs')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_isolation_id')->nullable()->constrained('service_isolations')->nullOnDelete();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 30);
            $table->string('address_list_name', 100)->nullable();
            $table->string('target_address', 45)->nullable();
            $table->string('router_item_id', 100)->nullable();
            $table->json('context')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('operation_job_id');
            $table->index('service_id');
            $table->index('service_isolation_id');
            $table->index('router_id');
            $table->index('action_type');
            $table->index(['router_id', 'action_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_router_operation_logs');
        Schema::dropIfExists('service_router_operation_jobs');
    }
};
