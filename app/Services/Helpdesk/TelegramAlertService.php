<?php

namespace App\Services\Helpdesk;

use App\Enums\TelegramLogStatus;
use App\Models\ServiceIsolation;
use App\Models\ServiceRouterOperationJob;
use App\Models\TelegramLog;
use Illuminate\Support\Facades\Log;

class TelegramAlertService
{
    public function queueIsolationConflictAlert(
        ServiceRouterOperationJob $operationJob,
        ServiceIsolation $serviceIsolation,
        string $conflictType,
    ): TelegramLog {
        $service = $operationJob->service;
        $customerCode = $service?->customer?->customer_code ?? 'unknown';
        $serviceCode = $service?->service_code ?? 'unknown';
        $targetAddress = $operationJob->target_address ?? 'unknown';
        $addressListName = $operationJob->address_list_name ?? 'unknown';

        $message = $this->buildIsolationConflictMessage(
            $operationJob->id,
            $customerCode,
            $serviceCode,
            $targetAddress,
            $addressListName,
            $serviceIsolation->status->value,
            $conflictType,
        );

        $payload = [
            'event' => 'isolation.conflict_detected',
            'operation_job_id' => $operationJob->id,
            'service_isolation_id' => $serviceIsolation->id,
            'service_id' => $operationJob->service_id,
            'router_id' => $operationJob->router_id,
            'customer_code' => $customerCode,
            'service_code' => $serviceCode,
            'target_address' => $targetAddress,
            'address_list_name' => $addressListName,
            'current_isolation_status' => $serviceIsolation->status->value,
            'conflict_type' => $conflictType,
            'created_at' => now()->toISOString(),
        ];

        return TelegramLog::query()->create([
            'channel' => 'telegram',
            'target' => $this->resolveHelpdeskTarget(),
            'message' => $message,
            'payload' => $payload,
            'status' => TelegramLogStatus::Queued->value,
        ]);
    }

    public function processQueuedAlerts(int $limit = 50): array
    {
        $summary = ['checked' => 0, 'processed' => 0, 'failed' => 0];

        $ids = TelegramLog::query()
            ->where('payload', 'like', '%"event":"isolation.conflict_detected"%')
            ->queued()
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->pluck('id');

        foreach ($ids as $id) {
            $summary['checked']++;
            $telegramLog = TelegramLog::query()->find($id);

            if ($telegramLog === null) {
                continue;
            }

            try {
                $this->processAlert($telegramLog);
                $summary['processed']++;
            } catch (\Throwable $throwable) {
                report($throwable);
                $summary['failed']++;

                $this->markAsFailed($telegramLog, $throwable);

                Log::channel(config('services.telegram.log_channel', config('logging.default')))
                    ->error('Telegram isolation conflict alert processing failed.', [
                        'telegram_log_id' => $telegramLog->id,
                        'message' => $throwable->getMessage(),
                    ]);
            }
        }

        return $summary;
    }

    public function processAlert(TelegramLog $telegramLog): TelegramLog
    {
        if ($telegramLog->status === TelegramLogStatus::Processed) {
            return $telegramLog;
        }

        Log::channel(config('services.telegram.log_channel', config('logging.default')))
            ->info('Telegram isolation conflict alert processed in log-only mode.', [
                'telegram_log_id' => $telegramLog->id,
                'target' => $telegramLog->target,
                'message' => $telegramLog->message,
                'payload' => $telegramLog->payload,
            ]);

        $payload = $telegramLog->payload ?? [];
        $payload['delivery_mode'] = 'log_only';
        $payload['processed_at'] = now()->toISOString();

        $telegramLog->update([
            'status' => TelegramLogStatus::Processed->value,
            'processed_at' => now(),
            'payload' => $payload,
        ]);

        return $telegramLog->refresh();
    }

    public function markAsFailed(TelegramLog $telegramLog, \Throwable $throwable): TelegramLog
    {
        $payload = $telegramLog->payload ?? [];
        $payload['error'] = $throwable->getMessage();
        $payload['failed_at'] = now()->toISOString();

        $telegramLog->update([
            'status' => TelegramLogStatus::Failed->value,
            'payload' => $payload,
        ]);

        return $telegramLog->refresh();
    }

    private function buildIsolationConflictMessage(
        int $operationJobId,
        string $customerCode,
        string $serviceCode,
        string $targetAddress,
        string $addressListName,
        string $isolationStatus,
        string $conflictType,
    ): string {
        $timestamp = now()->format('Y-m-d H:i:s');

        $message = implode("\n", [
            "⚠️ ISOLATION STATE CONFLICT DETECTED",
            "",
            "━━━━━━━━━━━━━━━━━━━━━━",
            "Time: {$timestamp}",
            "━━━━━━━━━━━━━━━━━━━━━━",
            "Job ID: #{$operationJobId}",
            "Customer: {$customerCode}",
            "Service: {$serviceCode}",
            "",
            "Router Target:",
            "  Address: {$targetAddress}",
            "  List: {$addressListName}",
            "",
            "Conflict Type: {$conflictType}",
            "Current Status: {$isolationStatus}",
            "",
            "⚠️ Action Required: IP may be isolated in router",
            "   but DB state is inconsistent. Admin check needed.",
            "",
            "Generated by Feralix Billing System",
        ]);

        return $message;
    }

    private function resolveHelpdeskTarget(): ?string
    {
        return config('services.telegram.helpdesk_group_id')
            ?? config('services.telegram.helpdesk_group_name');
    }
}