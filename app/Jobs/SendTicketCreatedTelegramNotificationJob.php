<?php

namespace App\Jobs;

use App\Enums\TelegramLogStatus;
use App\Models\TelegramLog;
use App\Services\Helpdesk\TelegramNotificationService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendTicketCreatedTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithAutomationLogging, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(private readonly int $telegramLogId)
    {
        $this->onQueue(config('automation.queues.notifications', 'notifications'));
    }

    public function handle(TelegramNotificationService $telegramNotificationService): void
    {
        $telegramLog = TelegramLog::query()->find($this->telegramLogId);

        if ($telegramLog === null) {
            return;
        }

        $this->logAutomationStarted('notifications.send_ticket_created_telegram', [
            'telegram_log_id' => $telegramLog->id,
            'ticket_id' => $telegramLog->ticket_id,
        ]);

        $telegramNotificationService->process($telegramLog);

        $this->logAutomationFinished('notifications.send_ticket_created_telegram', [
            'telegram_log_id' => $telegramLog->id,
            'ticket_id' => $telegramLog->ticket_id,
            'status' => TelegramLogStatus::Processed->value,
        ]);
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function failed(Throwable $exception): void
    {
        $telegramLog = TelegramLog::query()->find($this->telegramLogId);

        if ($telegramLog === null) {
            return;
        }

        $payload = $telegramLog->payload ?? [];
        $payload['error'] = $exception->getMessage();

        $telegramLog->update([
            'status' => TelegramLogStatus::Failed->value,
            'payload' => $payload,
        ]);

        $this->logAutomationFailed('notifications.send_ticket_created_telegram', $exception, [
            'telegram_log_id' => $telegramLog->id,
            'ticket_id' => $telegramLog->ticket_id,
        ]);
    }
}
