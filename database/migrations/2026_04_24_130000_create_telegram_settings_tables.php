<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_bots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->string('bot_name', 150);
            $table->text('token');
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index('router_id');
            $table->index('status');
        });

        Schema::create('telegram_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telegram_bot_id')->constrained('telegram_bots')->cascadeOnDelete();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->string('group_name', 150);
            $table->string('chat_id', 120);
            $table->string('group_type', 30);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index('telegram_bot_id');
            $table->index('router_id');
            $table->index(['group_type', 'status']);
            $table->unique(['telegram_bot_id', 'chat_id', 'group_type'], 'telegram_groups_bot_chat_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_groups');
        Schema::dropIfExists('telegram_bots');
    }
};
