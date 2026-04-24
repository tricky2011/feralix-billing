<?php

namespace App\Services\Helpdesk;

use App\Enums\TelegramLogStatus;
use App\Models\TelegramLog;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramNotificationService
{
    private const TICKET_RELATIONS = [
        'customer:id,customer_code,full_name',
        'service:id,customer_id,service_code',
        'assignedTechnician:id,technician_code,full_name,is_active',
    ];

    public function queueTicketCreatedNotification(Ticket $ticket): TelegramLog
    {
        $ticket->loadMissing(self::TICKET_RELATIONS);

        return $ticket->telegramLogs()->create([
            'channel' => 'telegram',
            'target' => $this->resolveHelpdeskTarget(),
            'message' => $this->buildTicketCreatedMessage($ticket),
            'payload' => $this->buildTicketCreatedPayload($ticket),
            'status' => TelegramLogStatus::Queued->value,
        ]);
    }

    public function queueTicketStatusChangedNotification(Ticket $ticket, string $previousStatus, ?string $notes = null): TelegramLog
    {
        $ticket->loadMissing(self::TICKET_RELATIONS);

        return $ticket->telegramLogs()->create([
            'channel' => 'telegram',
            'target' => $this->resolveHelpdeskTarget(),
            'message' => $this->buildTicketStatusChangedMessage($ticket, $previousStatus, $notes),
            'payload' => [
                ...$this->buildTicketCreatedPayload($ticket),
                'event' => 'ticket.status_changed',
                'previous_status' => $previousStatus,
                'notes' => $notes,
            ],
            'status' => TelegramLogStatus::Queued->value,
        ]);
    }

    public function queueTicketReplyNotification(Ticket $ticket, string $body): TelegramLog
    {
        $ticket->loadMissing(self::TICKET_RELATIONS);

        return $ticket->telegramLogs()->create([
            'channel' => 'telegram',
            'target' => $this->resolveHelpdeskTarget(),
            'message' => $this->buildTicketReplyMessage($ticket, $body),
            'payload' => [
                ...$this->buildTicketCreatedPayload($ticket),
                'event' => 'ticket.reply_created',
                'reply_excerpt' => mb_substr($body, 0, 240),
            ],
            'status' => TelegramLogStatus::Queued->value,
        ]);
    }

    public function process(TelegramLog $telegramLog): TelegramLog
    {
        if ($telegramLog->status === TelegramLogStatus::Processed) {
            return $telegramLog;
        }

        $telegramLog->loadMissing([
            'ticket.customer:id,customer_code,full_name',
            'ticket.service:id,customer_id,service_code',
            'ticket.assignedTechnician:id,technician_code,full_name,is_active',
        ]);

        Log::channel(config('services.telegram.log_channel', config('logging.default')))
            ->info('Telegram ticket notification processed in log-only mode.', [
                'telegram_log_id' => $telegramLog->id,
                'ticket_id' => $telegramLog->ticket_id,
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

    /**
     * @return array{checked:int,processed:int,failed:int}
     */
    public function processQueued(int $limit = 100): array
    {
        $summary = [
            'checked' => 0,
            'processed' => 0,
            'failed' => 0,
        ];

        $ids = TelegramLog::query()
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
                $this->process($telegramLog);
                $summary['processed']++;
            } catch (Throwable $throwable) {
                report($throwable);
                $summary['failed']++;

                $this->markAsFailed($telegramLog, $throwable);

                Log::channel(config('services.telegram.log_channel', config('logging.default')))
                    ->error('Telegram queued notification processing failed.', [
                        'telegram_log_id' => $telegramLog->id,
                        'ticket_id' => $telegramLog->ticket_id,
                        'message' => $throwable->getMessage(),
                    ]);
            }
        }

        return $summary;
    }

    public function markAsFailed(TelegramLog $telegramLog, Throwable $throwable): TelegramLog
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

    private function buildTicketCreatedMessage(Ticket $ticket): string
    {
        $serviceCode = $ticket->service?->service_code ?? '-';
        $technicianName = $ticket->assignedTechnician?->full_name ?? 'Belum ter-assign';

        return implode("\n", [
            'Ticket helpdesk baru dibuat.',
            "No Ticket: {$ticket->ticket_number}",
            "Customer: {$ticket->customer->customer_code} - {$ticket->customer->full_name}",
            "Service: {$serviceCode}",
            "Kategori: {$ticket->category}",
            "Prioritas: {$ticket->priority?->value}",
            "Status: {$ticket->status?->value}",
            "Teknisi: {$technicianName}",
            "Deskripsi: {$ticket->description}",
        ]);
    }

    private function buildTicketStatusChangedMessage(Ticket $ticket, string $previousStatus, ?string $notes): string
    {
        return implode("\n", array_filter([
            'Status ticket helpdesk berubah.',
            "No Ticket: {$ticket->ticket_number}",
            "Customer: {$ticket->customer->customer_code} - {$ticket->customer->full_name}",
            "Status sebelumnya: {$previousStatus}",
            "Status baru: {$ticket->status?->value}",
            $notes !== null && $notes !== '' ? "Catatan: {$notes}" : null,
        ]));
    }

    private function buildTicketReplyMessage(Ticket $ticket, string $body): string
    {
        return implode("\n", [
            'Reply ticket helpdesk baru.',
            "No Ticket: {$ticket->ticket_number}",
            "Customer: {$ticket->customer->customer_code} - {$ticket->customer->full_name}",
            'Reply: '.mb_substr($body, 0, 240),
        ]);
    }

    private function buildTicketCreatedPayload(Ticket $ticket): array
    {
        return [
            'event' => 'ticket.created',
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'category' => $ticket->category,
            'priority' => $ticket->priority?->value,
            'status' => $ticket->status?->value,
            'assignment_mode' => $ticket->assignment_mode?->value,
            'description' => $ticket->description,
            'customer' => [
                'id' => $ticket->customer?->id,
                'customer_code' => $ticket->customer?->customer_code,
                'full_name' => $ticket->customer?->full_name,
            ],
            'service' => $ticket->service === null ? null : [
                'id' => $ticket->service->id,
                'service_code' => $ticket->service->service_code,
            ],
            'assigned_technician' => $ticket->assignedTechnician === null ? null : [
                'id' => $ticket->assignedTechnician->id,
                'technician_code' => $ticket->assignedTechnician->technician_code,
                'full_name' => $ticket->assignedTechnician->full_name,
            ],
        ];
    }

    private function resolveHelpdeskTarget(): ?string
    {
        return config('services.telegram.helpdesk_group_id')
            ?? config('services.telegram.helpdesk_group_name');
    }
}
