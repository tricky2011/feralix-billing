<?php

namespace App\Services\Dashboard;

use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Access\RoleRouterScopeService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TechnicianDashboardService
{
    private const DEFAULT_INSTALLATION_TARGET = 20;

    private const DEFAULT_TICKET_TARGET = 50;

    private const COMPLETED_WORK_ORDER_STATUSES = [
        'completed',
        'closed',
        'resolved',
        'done',
        'finished',
    ];

    private const CLOSED_TICKET_STATUSES = [
        'closed',
        'resolved',
        'completed',
        'done',
        'finished',
    ];

    private const COMPLETED_WORK_ORDER_POINT = 10;

    private const CLOSED_TICKET_POINT = 5;

    public function __construct(private readonly RoleRouterScopeService $roleRouterScopeService) {}

    public function build(User $user, array $filters): array
    {
        [
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ] = $this->resolvePeriodWindow($filters);

        $selectedTechnicianId = $this->resolveSelectedTechnicianId(
            $user,
            $filters['technician_id'] ?? null,
        );

        $technicians = $this->technicianFilterOptions($user, $selectedTechnicianId);

        $workOrdersQuery = $this->baseWorkOrdersQuery(
            $user,
            $selectedTechnicianId,
            $start,
            $end,
        );

        $ticketsQuery = $this->baseTicketsQuery(
            $user,
            $selectedTechnicianId,
            $start,
            $end,
        );

        $totalInstallations = (clone $workOrdersQuery)->count();
        $completedInstallations = $this->applyCompletedWorkOrderStatus((clone $workOrdersQuery))->count();
        $closedTickets = $this->applyClosedTicketStatus((clone $ticketsQuery))->count();
        $openTickets = $this->applyOpenTicketStatus((clone $ticketsQuery))->count();

        $totalPoints =
            ($completedInstallations * self::COMPLETED_WORK_ORDER_POINT)
            + ($closedTickets * self::CLOSED_TICKET_POINT);

        $targets = $this->resolveTargets();

        return [
            'filters' => [
                'technician_id' => $selectedTechnicianId,
                'period' => $period,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'technicians' => $technicians,
            ],
            'summary' => [
                'total_installations' => $totalInstallations,
                'completed_installations' => $completedInstallations,
                'open_tickets' => $openTickets,
                'closed_tickets' => $closedTickets,
                'total_points' => $totalPoints,
            ],
            'targets' => $targets,
            'ranking' => $this->buildRanking(
                $user,
                $selectedTechnicianId,
                $start,
                $end,
                $technicians,
            ),
            'work_orders' => $this->buildWorkOrderRows(
                $user,
                $selectedTechnicianId,
                $start,
                $end,
            ),
            'tickets' => $this->buildTicketRows(
                $user,
                $selectedTechnicianId,
                $start,
                $end,
            ),
            'point_rules' => [
                [
                    'code' => 'completed_work_order',
                    'label' => 'Completed installation/work order',
                    'points' => self::COMPLETED_WORK_ORDER_POINT,
                ],
                [
                    'code' => 'closed_ticket',
                    'label' => 'Closed ticket',
                    'points' => self::CLOSED_TICKET_POINT,
                ],
                [
                    'code' => 'open_ticket',
                    'label' => 'Unresolved/open ticket',
                    'points' => 0,
                ],
            ],
        ];
    }

    /**
     * @return array{period:string,start:CarbonImmutable,end:CarbonImmutable,date_from:string,date_to:string}
     */
    private function resolvePeriodWindow(array $filters): array
    {
        $period = $filters['period'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if (($dateFrom !== null || $dateTo !== null) && $period === null) {
            $period = 'custom';
        }

        $period ??= 'month';

        $now = CarbonImmutable::now();

        return match ($period) {
            'today' => [
                'period' => 'today',
                'start' => $now->startOfDay(),
                'end' => $now->endOfDay(),
                'date_from' => $now->toDateString(),
                'date_to' => $now->toDateString(),
            ],
            'week' => [
                'period' => 'week',
                'start' => $now->startOfWeek(),
                'end' => $now->endOfWeek(),
                'date_from' => $now->startOfWeek()->toDateString(),
                'date_to' => $now->endOfWeek()->toDateString(),
            ],
            'custom' => [
                'period' => 'custom',
                'start' => CarbonImmutable::parse((string) $dateFrom)->startOfDay(),
                'end' => CarbonImmutable::parse((string) $dateTo)->endOfDay(),
                'date_from' => CarbonImmutable::parse((string) $dateFrom)->toDateString(),
                'date_to' => CarbonImmutable::parse((string) $dateTo)->toDateString(),
            ],
            default => [
                'period' => 'month',
                'start' => $now->startOfMonth(),
                'end' => $now->endOfMonth(),
                'date_from' => $now->startOfMonth()->toDateString(),
                'date_to' => $now->endOfMonth()->toDateString(),
            ],
        };
    }

    private function resolveSelectedTechnicianId(User $user, mixed $requestedTechnicianId): ?int
    {
        if ($user->isTechnician()) {
            if ($user->technician_id === null) {
                throw new AuthorizationException('Technician profile is not linked to this account.');
            }

            return (int) $user->technician_id;
        }

        if ($requestedTechnicianId === null || $requestedTechnicianId === '') {
            return null;
        }

        return (int) $requestedTechnicianId;
    }

    /**
     * @return list<array{id:int,user_id:int|null,technician_code:string,full_name:string}>
     */
    private function technicianFilterOptions(User $user, ?int $selectedTechnicianId): array
    {
        $query = User::query()
            ->with('technician:id,technician_code,full_name,is_active')
            ->where('role', UserRole::Technician->value)
            ->where('is_active', true)
            ->whereNotNull('technician_id');

        if ($user->isTechnician() && $user->technician_id !== null) {
            $query->where('technician_id', $user->technician_id);
        }

        $rows = $query
            ->orderBy('name')
            ->get()
            ->map(static function (User $technicianUser): ?array {
                $technician = $technicianUser->technician;

                if ($technician === null) {
                    return null;
                }

                return [
                    'id' => (int) $technician->id,
                    'user_id' => $technicianUser->id,
                    'technician_code' => (string) $technician->technician_code,
                    'full_name' => (string) ($technician->full_name ?: $technicianUser->name),
                ];
            })
            ->filter()
            ->unique('id')
            ->values();

        if ($selectedTechnicianId !== null && ! $rows->contains('id', $selectedTechnicianId)) {
            $fallback = Technician::query()->find($selectedTechnicianId);

            if ($fallback !== null) {
                $rows->push([
                    'id' => (int) $fallback->id,
                    'user_id' => null,
                    'technician_code' => (string) $fallback->technician_code,
                    'full_name' => (string) $fallback->full_name,
                ]);
            }
        }

        return $rows->all();
    }

    private function baseWorkOrdersQuery(
        User $user,
        ?int $technicianId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Builder {
        $query = WorkOrder::query()
            ->whereBetween('created_at', [
                $start->toDateTimeString(),
                $end->toDateTimeString(),
            ]);

        if ($technicianId !== null) {
            $query->where('assigned_technician_id', $technicianId);
        }

        if (! $user->isTechnician()) {
            $this->roleRouterScopeService->applyRouterScope($query, 'router_id', $user);
        }

        return $query;
    }

    private function baseTicketsQuery(
        User $user,
        ?int $technicianId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Builder {
        $query = Ticket::query()
            ->whereBetween('created_at', [
                $start->toDateTimeString(),
                $end->toDateTimeString(),
            ]);

        if ($technicianId !== null) {
            $query->where('assigned_technician_id', $technicianId);
        }

        if (! $user->isTechnician()) {
            $this->roleRouterScopeService->applyServiceRouterScope(
                $query,
                relation: 'service',
                qualifiedColumn: 'router_id',
                nullableForeignKey: 'service_id',
                candidate: $user,
            );
        }

        return $query;
    }

    private function applyCompletedWorkOrderStatus(Builder $query): Builder
    {
        return $query->whereIn(DB::raw('LOWER(status)'), self::COMPLETED_WORK_ORDER_STATUSES);
    }

    private function applyClosedTicketStatus(Builder $query): Builder
    {
        return $query->whereIn(DB::raw('LOWER(status)'), self::CLOSED_TICKET_STATUSES);
    }

    private function applyOpenTicketStatus(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder
                ->whereNull('status')
                ->orWhereNotIn(DB::raw('LOWER(status)'), self::CLOSED_TICKET_STATUSES);
        });
    }

    /**
     * @param  list<array{id:int,user_id:int|null,technician_code:string,full_name:string}>  $technicians
     * @return list<array{rank:int,technician_id:int,technician_code:string,technician_name:string,full_name:string,completed_installations:int,closed_tickets:int,points:int}>
     */
    private function buildRanking(
        User $user,
        ?int $selectedTechnicianId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $technicians,
    ): array {
        $completedInstallationsByTechnician = $this->applyCompletedWorkOrderStatus(
            (clone $this->baseWorkOrdersQuery($user, $selectedTechnicianId, $start, $end))
        )
            ->whereNotNull('assigned_technician_id')
            ->selectRaw('assigned_technician_id, COUNT(*) as aggregate')
            ->groupBy('assigned_technician_id')
            ->pluck('aggregate', 'assigned_technician_id');

        $closedTicketsByTechnician = $this->applyClosedTicketStatus(
            (clone $this->baseTicketsQuery($user, $selectedTechnicianId, $start, $end))
        )
            ->whereNotNull('assigned_technician_id')
            ->selectRaw('assigned_technician_id, COUNT(*) as aggregate')
            ->groupBy('assigned_technician_id')
            ->pluck('aggregate', 'assigned_technician_id');

        $ranking = collect($technicians)
            ->map(function (array $technician) use ($completedInstallationsByTechnician, $closedTicketsByTechnician): array {
                $technicianId = (int) $technician['id'];
                $completedInstallations = (int) ($completedInstallationsByTechnician[$technicianId] ?? 0);
                $closedTickets = (int) ($closedTicketsByTechnician[$technicianId] ?? 0);
                $points =
                    ($completedInstallations * self::COMPLETED_WORK_ORDER_POINT)
                    + ($closedTickets * self::CLOSED_TICKET_POINT);

                return [
                    'technician_id' => $technicianId,
                    'technician_code' => $technician['technician_code'],
                    'technician_name' => $technician['full_name'],
                    'full_name' => $technician['full_name'],
                    'completed_installations' => $completedInstallations,
                    'closed_tickets' => $closedTickets,
                    'points' => $points,
                ];
            })
            ->values()
            ->all();

        usort($ranking, static function (array $left, array $right): int {
            if ($left['points'] !== $right['points']) {
                return $right['points'] <=> $left['points'];
            }

            if ($left['completed_installations'] !== $right['completed_installations']) {
                return $right['completed_installations'] <=> $left['completed_installations'];
            }

            if ($left['closed_tickets'] !== $right['closed_tickets']) {
                return $right['closed_tickets'] <=> $left['closed_tickets'];
            }

            return strcmp(
                strtolower((string) $left['technician_name']),
                strtolower((string) $right['technician_name']),
            );
        });

        foreach ($ranking as $index => &$row) {
            $row['rank'] = $index + 1;
        }

        return $ranking;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildWorkOrderRows(
        User $user,
        ?int $technicianId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        return (clone $this->baseWorkOrdersQuery($user, $technicianId, $start, $end))
            ->with([
                'customer:id,customer_code,full_name',
                'router:id,router_code,router_name',
                'assignedTechnician:id,technician_code,full_name',
            ])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static function (WorkOrder $workOrder): array {
                return [
                    'id' => $workOrder->id,
                    'wo_number' => $workOrder->wo_number,
                    'wo_type' => self::enumValue($workOrder->wo_type),
                    'status' => self::enumValue($workOrder->status),
                    'assigned_technician_id' => $workOrder->assigned_technician_id,
                    'assigned_technician' => $workOrder->assignedTechnician === null ? null : [
                        'id' => $workOrder->assignedTechnician->id,
                        'technician_code' => $workOrder->assignedTechnician->technician_code,
                        'full_name' => $workOrder->assignedTechnician->full_name,
                    ],
                    'customer' => $workOrder->customer === null ? null : [
                        'id' => $workOrder->customer->id,
                        'customer_code' => $workOrder->customer->customer_code,
                        'full_name' => $workOrder->customer->full_name,
                    ],
                    'router' => $workOrder->router === null ? null : [
                        'id' => $workOrder->router->id,
                        'router_code' => $workOrder->router->router_code,
                        'router_name' => $workOrder->router->router_name,
                    ],
                    'scheduled_at' => $workOrder->scheduled_at?->toISOString(),
                    'completed_at' => $workOrder->completed_at?->toISOString(),
                    'created_at' => $workOrder->created_at?->toISOString(),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildTicketRows(
        User $user,
        ?int $technicianId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        return (clone $this->baseTicketsQuery($user, $technicianId, $start, $end))
            ->with([
                'customer:id,customer_code,full_name',
                'service:id,service_code,router_id',
                'assignedTechnician:id,technician_code,full_name',
            ])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static function (Ticket $ticket): array {
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'category' => $ticket->category,
                    'priority' => self::enumValue($ticket->priority),
                    'status' => self::enumValue($ticket->status),
                    'assigned_technician_id' => $ticket->assigned_technician_id,
                    'assigned_technician' => $ticket->assignedTechnician === null ? null : [
                        'id' => $ticket->assignedTechnician->id,
                        'technician_code' => $ticket->assignedTechnician->technician_code,
                        'full_name' => $ticket->assignedTechnician->full_name,
                    ],
                    'customer' => $ticket->customer === null ? null : [
                        'id' => $ticket->customer->id,
                        'customer_code' => $ticket->customer->customer_code,
                        'full_name' => $ticket->customer->full_name,
                    ],
                    'service' => $ticket->service === null ? null : [
                        'id' => $ticket->service->id,
                        'service_code' => $ticket->service->service_code,
                        'router_id' => $ticket->service->router_id,
                    ],
                    'created_at' => $ticket->created_at?->toISOString(),
                ];
            })
            ->all();
    }

    /**
     * @return array{installation_target:int,ticket_target:int}
     */
    private function resolveTargets(): array
    {
        return [
            'installation_target' => $this->readIntegerSetting(
                'technician_dashboard',
                'installation_target',
                self::DEFAULT_INSTALLATION_TARGET,
            ),
            'ticket_target' => $this->readIntegerSetting(
                'technician_dashboard',
                'ticket_target',
                self::DEFAULT_TICKET_TARGET,
            ),
        ];
    }

    private function readIntegerSetting(string $group, string $key, int $default): int
    {
        $setting = SystemSetting::query()
            ->where('setting_group', $group)
            ->where('setting_key', $key)
            ->first();

        if ($setting === null) {
            return $default;
        }

        $value = (int) ($setting->value() ?? $default);

        return $value > 0
            ? $value
            : $default;
    }

    private static function enumValue(BackedEnum|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof BackedEnum
            ? (string) $value->value
            : (string) $value;
    }
}
