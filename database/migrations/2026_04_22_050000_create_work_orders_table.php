<?php

use App\Enums\WorkOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('router_id')->constrained();
            $table->foreignId('olt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ont_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wo_number', 50)->unique();
            $table->string('wo_type', 30);
            $table->foreignId('assigned_technician_id')->nullable()->constrained('technicians')->nullOnDelete();
            $table->unsignedSmallInteger('planned_monitor_vid')->nullable();
            $table->unsignedSmallInteger('planned_internet_vid')->nullable();
            $table->string('planned_subnet_cidr', 50)->nullable();
            $table->string('status', 30)->default(WorkOrderStatus::Open->value);
            $table->text('work_notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('service_id');
            $table->index('router_id');
            $table->index('olt_id');
            $table->index('ont_id');
            $table->index('wo_type');
            $table->index('assigned_technician_id');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('completed_at');
            $table->index(['customer_id', 'status']);
            $table->index(['assigned_technician_id', 'status']);
            $table->index(['router_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
