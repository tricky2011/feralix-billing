<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technicians', function (Blueprint $table): void {
            $table->id();
            $table->string('technician_code', 30)->unique();
            $table->string('full_name', 150);
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_assigned_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('last_assigned_at');
            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technicians');
    }
};
