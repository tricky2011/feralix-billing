<?php

namespace App\Services\Helpdesk;

use App\Enums\TicketAssignmentMode;
use App\Enums\TicketStatus;
use App\Jobs\SendTicketCreatedTelegramNotificationJob;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketService
{
    private const INDEX_RELATIONS = [
        'customer:id,customer_code,full_name',
        'service:id,customer_id,service_code',
        'assignedTechnician:id,technician_code,full_name,is_active',
    ];

    public function __construct(
        private readonly TechnicianAutoAssignmentService $technicianAutoAssignmentService,
        private readonly TelegramNotificationService $telegramNotificationService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return Ticket::query()
            ->with(self::INDEX_RELATIONS)
            ->search($filters['search'] ?? null)
            ->when(
                $filters['customer_id'] ?? null,
                fn (Builder $builder, $customerId) => $builder->where('customer_id', $customerId)
            )
            ->when(
                $filters['service_id'] ?? null,
                fn (Builder $builder, $serviceId) => $builder->where('service_id', $serviceId)
            )
            ->when(
                $filters['assigned_technician_id'] ?? null,
                fn (Builder $builder, $technicianId) => $builder->where('assigned_technician_id', $technicianId)
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $builder, $status) => $builder->where('status', $status)
            )
            ->when(
                $filters['priority'] ?? null,
                fn (Builder $builder, $priority) => $builder->where('priority', $priority)
            )
            ->when(
                $filters['assignment_mode'] ?? null,
                fn (Builder $builder, $assignmentMode) => $builder->where('assignment_mode', $assignmentMode)
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $payload): Ticket
    {
        $result = DB::transaction(function () use ($payload): array {
            $technician = $this->technicianAutoAssignmentService->assign();

            $ticket = Ticket::query()->create([
                'customer_id' => $payload['customer_id'],
                'service_id' => $payload['service_id'] ?? null,
                'ticket_number' => $this->generateTicketNumber(),
                'category' => $payload['category'],
                'priority' => $payload['priority'],
                'description' => $payload['description'],
                'assigned_technician_id' => $technician?->id,
                'assignment_mode' => TicketAssignmentMode::Auto->value,
                'status' => $technician !== null
                    ? TicketStatus::Assigned->value
                    : TicketStatus::Open->value,
            ]);

            $telegramLog = $this->telegramNotificationService->queueTicketCreatedNotification($ticket);

            return [
                'ticket_id' => $ticket->id,
                'telegram_log_id' => $telegramLog->id,
            ];
        });

        SendTicketCreatedTelegramNotificationJob::dispatch($result['telegram_log_id']);

        return $this->loadTicket(
            Ticket::query()->findOrFail($result['ticket_id']),
            includeTelegramLogs: true
        );
    }

    public function find(Ticket $ticket): Ticket
    {
        return $this->loadTicket($ticket, includeTelegramLogs: true);
    }

    private function loadTicket(Ticket $ticket, bool $includeTelegramLogs = false): Ticket
    {
        $ticket = $ticket->refresh();

        $relations = self::INDEX_RELATIONS;

        if ($includeTelegramLogs) {
            $relations['telegramLogs'] = fn ($query) => $query->orderByDesc('id');
        }

        $ticket->loadMissing($relations);

        return $ticket;
    }

    private function generateTicketNumber(): string
    {
        do {
            $ticketNumber = sprintf(
                'TCK-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(6))
            );
        } while (Ticket::query()->where('ticket_number', $ticketNumber)->exists());

        return $ticketNumber;
    }
}
