<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pon_ports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete();
            $table->unsignedTinyInteger('port_number');
            $table->string('name', 50)->nullable();
            $table->unsignedInteger('max_capacity')->default(100);
            $table->unsignedInteger('current_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['olt_id', 'port_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pon_ports');
    }
};