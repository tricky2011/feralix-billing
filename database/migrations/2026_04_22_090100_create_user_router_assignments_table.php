<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_router_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'router_id']);
            $table->index('router_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_router_assignments');
    }
};
