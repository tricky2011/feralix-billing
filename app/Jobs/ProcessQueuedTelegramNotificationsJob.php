<?php

namespace App\Jobs;

use App\Services\Helpdesk\TelegramNotificationService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessQueuedTelegramNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithAutomationLogging, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(private readonly int $limit = 100)
    {
        $this->onQueue(config('automation.queues.notifications', 'notifications'));
    }

    public function handle(TelegramNotificationService $telegramNotificationService): void
    {
        $this->logAutomationStarted('notifications.process_telegram', [
            'limit' => $this->limit,
        ]);

        $summary = $telegramNotificationService->processQueued($this->limit);

        $this->logAutomationFinished('notifications.process_telegram', $summary);
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('notifications.process_telegram', $exception, [
            'limit' => $this->limit,
        ]);
    }
}
