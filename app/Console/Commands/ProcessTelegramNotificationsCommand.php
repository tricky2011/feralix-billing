<?php

namespace App\Console\Commands;

use App\Jobs\ProcessQueuedTelegramNotificationsJob;
use App\Services\Helpdesk\TelegramNotificationService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Console\Command;
use Throwable;

class ProcessTelegramNotificationsCommand extends Command
{
    use InteractsWithAutomationLogging;

    protected $signature = 'notifications:process-telegram
        {--limit= : Maximum queued Telegram notifications to process}
        {--queue : Dispatch queued processing to the notifications queue}';

    protected $description = 'Process queued Telegram notification logs.';

    public function handle(TelegramNotificationService $telegramNotificationService): int
    {
        $limit = max(1, (int) ($this->option('limit') ?: config('automation.notifications.telegram.batch_limit', 100)));

        if ((bool) $this->option('queue')) {
            ProcessQueuedTelegramNotificationsJob::dispatch($limit);

            $this->info(sprintf('Queued Telegram notification processing with limit %d.', $limit));

            return self::SUCCESS;
        }

        try {
            $summary = $telegramNotificationService->processQueued($limit);
        } catch (Throwable $throwable) {
            report($throwable);
            $this->logAutomationFailed('notifications.process_telegram.command', $throwable, [
                'limit' => $limit,
            ]);
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Checked %d queued Telegram notification(s), processed %d, failed %d.',
            $summary['checked'],
            $summary['processed'],
            $summary['failed'],
        ));

        return $summary['failed'] === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
