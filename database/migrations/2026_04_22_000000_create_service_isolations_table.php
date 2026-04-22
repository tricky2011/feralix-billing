<?php

use App\Enums\ServiceIsolationStatus;
use App\Enums\ServiceIsolationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_isolations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('router_id')->constrained();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('isolation_type', 50)->default(ServiceIsolationType::Manual->value);
            $table->string('address_list_name', 100);
            $table->string('target_subnet', 50);
            $table->string('redirect_url', 255)->nullable();
            $table->string('status', 30)->default(ServiceIsolationStatus::Pending->value);
            $table->timestamp('isolated_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('service_id');
            $table->index('router_id');
            $table->index('invoice_id');
            $table->index('status');
            $table->index('isolated_at');
            $table->index('released_at');
            $table->index('created_by');
            $table->index(['service_id', 'status']);
            $table->index(['invoice_id', 'status']);
            $table->index(['router_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_isolations');
    }
};
