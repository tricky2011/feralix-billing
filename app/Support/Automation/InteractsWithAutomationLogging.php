<?php

namespace App\Support\Automation;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

trait InteractsWithAutomationLogging
{
    protected function automationLogChannel(): string
    {
        return (string) config('automation.logging.channel', config('logging.default', 'stack'));
    }

    protected function automationLogger(): LoggerInterface
    {
        return Log::channel($this->automationLogChannel());
    }

    protected function logAutomationStarted(string $task, array $context = []): void
    {
        $this->automationLogger()->info(sprintf('%s started.', $task), $context);
    }

    protected function logAutomationFinished(string $task, array $context = []): void
    {
        $this->automationLogger()->info(sprintf('%s finished.', $task), $context);
    }

    protected function logAutomationFailed(string $task, Throwable $throwable, array $context = []): void
    {
        $this->automationLogger()->error(sprintf('%s failed.', $task), [
            ...$context,
            'exception' => get_class($throwable),
            'message' => $throwable->getMessage(),
        ]);
    }
}
