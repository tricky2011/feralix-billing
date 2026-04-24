<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_group', 50)->default('app');
            $table->string('setting_key', 120)->unique();
            $table->text('setting_value')->nullable();
            $table->string('value_type', 20)->default('string');
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('setting_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
