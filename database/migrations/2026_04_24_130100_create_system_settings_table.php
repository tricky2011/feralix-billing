<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_group', 80);
            $table->string('setting_key', 120);
            $table->text('setting_value')->nullable();
            $table->string('value_type', 30)->default('string');
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            $table->unique(['setting_group', 'setting_key']);
            $table->index('setting_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
