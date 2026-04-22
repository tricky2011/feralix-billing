<?php

use App\Enums\TelegramLogStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 30)->default('telegram');
            $table->string('target', 255)->nullable();
            $table->text('message');
            $table->json('payload')->nullable();
            $table->string('status', 30)->default(TelegramLogStatus::Queued->value);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('ticket_id');
            $table->index('channel');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_logs');
    }
};
