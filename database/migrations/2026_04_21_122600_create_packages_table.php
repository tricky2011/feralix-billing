<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table): void {
            $table->id();
            $table->string('package_name', 150)->unique();
            $table->decimal('monthly_price', 15, 2);
            $table->unsignedTinyInteger('ip_pool_count')->default(5);
            $table->unsignedSmallInteger('rate_limit_mbps')->default(10);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
