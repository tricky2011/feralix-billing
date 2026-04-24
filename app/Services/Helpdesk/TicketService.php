<?php

namespace App\Services\Helpdesk;

use App\Enums\TicketAssignmentMode;
use App\Enums\TicketStatus;
use App\Jobs\SendTicketCreatedTelegramNotificationJob;
use App\Models\TicketReply;
use App\Models\Ticket;
use App\Services\Access\RoleRouterScopeService;
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
        private readonly RoleRouterScopeService $roleRouterScopeService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        if (($filters['service_id'] ?? null) !== null) {
            $this->roleRouterScopeService->findAccessibleService((int) $filters['service_id']);
        }

        if (($filters['customer_id'] ?? null) !== null) {
            $this->roleRouterScopeService->findAccessibleCustomer((int) $filters['customer_id']);
        }

        $query = Ticket::query()
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
            );

        $this->roleRouterScopeService->applyServiceRouterScope($query, 'service', 'router_id', 'service_id');

        return $query
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $payload): Ticket
    {
        $this->roleRouterScopeService->findAccessibleCustomer((int) $payload['customer_id']);

        if (($payload['service_id'] ?? null) !== null) {
            $this->roleRouterScopeService->findAccessibleService((int) $payload['service_id']);
        }

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
        $this->roleRouterScopeService->ensureModelAccessible($ticket);

        return $this->loadTicket($ticket, includeTelegramLogs: true);
    }

    public function updateStatus(Ticket $ticket, array $payload): Ticket
    {
        $this->roleRouterScopeService->ensureModelAccessible($ticket);

        $result = DB::transaction(function () use ($ticket, $payload): array {
            $lockedTicket = Ticket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            $previousStatus = $lockedTicket->status?->value ?? (string) $lockedTicket->status;
            $nextStatus = (string) $payload['status'];

            if ($previousStatus !== $nextStatus) {
                $lockedTicket->update([
                    'status' => $nextStatus,
                ]);

                $telegramLog = $this->telegramNotificationService->queueTicketStatusChangedNotification(
                    $lockedTicket->refresh(),
                    $previousStatus,
                    $payload['notes'] ?? null,
                );

                return [
                    'ticket_id' => $lockedTicket->id,
                    'telegram_log_id' => $telegramLog->id,
                ];
            }

            return [
                'ticket_id' => $lockedTicket->id,
                'telegram_log_id' => null,
            ];
        });

        if ($result['telegram_log_id'] !== null) {
            SendTicketCreatedTelegramNotificationJob::dispatch($result['telegram_log_id']);
        }

        return $this->loadTicket(
            Ticket::query()->findOrFail($result['ticket_id']),
            includeTelegramLogs: true,
        );
    }

    public function reply(Ticket $ticket, array $payload): TicketReply
    {
        $this->roleRouterScopeService->ensureModelAccessible($ticket);

        $result = DB::transaction(function () use ($ticket, $payload): array {
            $lockedTicket = Ticket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            $reply = $lockedTicket->replies()->create([
                'user_id' => auth()->id(),
                'body' => $payload['body'],
                'is_internal' => (bool) ($payload['is_internal'] ?? false),
            ]);

            $telegramLog = $this->telegramNotificationService->queueTicketReplyNotification(
                $lockedTicket,
                $reply->body,
            );

            return [
                'reply_id' => $reply->id,
                'telegram_log_id' => $telegramLog->id,
            ];
        });

        SendTicketCreatedTelegramNotificationJob::dispatch($result['telegram_log_id']);

        return TicketReply::query()
            ->with('user:id,name,role')
            ->findOrFail($result['reply_id']);
    }

    private function loadTicket(Ticket $ticket, bool $includeTelegramLogs = false): Ticket
    {
        $ticket = $ticket->refresh();

        $relations = self::INDEX_RELATIONS;

        if ($includeTelegramLogs) {
            $relations['telegramLogs'] = fn ($query) => $query->orderByDesc('id');
            $relations['replies'] = fn ($query) => $query
                ->with('user:id,name,role')
                ->orderBy('id');
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
