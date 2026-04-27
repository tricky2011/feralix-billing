<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_categories');
    }
};
