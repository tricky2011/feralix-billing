<?php

use App\Enums\TicketAssignmentMode;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticket_number', 50)->unique();
            $table->string('category', 100);
            $table->string('priority', 20)->default(TicketPriority::Medium->value);
            $table->text('description');
            $table->foreignId('assigned_technician_id')->nullable()->constrained('technicians')->nullOnDelete();
            $table->string('assignment_mode', 20)->default(TicketAssignmentMode::Auto->value);
            $table->string('status', 30)->default(TicketStatus::Open->value);
            $table->timestamps();

            $table->index('customer_id');
            $table->index('service_id');
            $table->index('assigned_technician_id');
            $table->index('priority');
            $table->index('assignment_mode');
            $table->index('status');
            $table->index(['customer_id', 'status']);
            $table->index(['assigned_technician_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
