<?php

namespace App\Services\Dashboard\Queries;

use App\Data\Dashboard\DashboardScope;
use App\Enums\TicketStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\WorkOrder;

class DashboardTechnicianRankingQuery extends AbstractDashboardQuery
{
    public function get(DashboardScope $scope): array
    {
        ['current_period' => $currentPeriod, 'current_start' => $currentStart, 'current_end' => $currentEnd] = $this->monthWindow();

        $workOrderSummary = WorkOrder::query()
            ->selectRaw('assigned_technician_id')
            ->selectRaw('COUNT(*) as completed_work_orders')
            ->selectRaw("SUM(CASE WHEN wo_type = {$this->quoteSqlString(WorkOrderType::NewInstall->value)} THEN 1 ELSE 0 END) as completed_installations")
            ->whereNotNull('assigned_technician_id')
            ->where('status', WorkOrderStatus::Completed->value)
            ->whereBetween('completed_at', [$currentStart, $currentEnd]);

        $this->applyRouterScope($workOrderSummary, $scope, 'router_id');

        $ticketSummary = Ticket::query()
            ->selectRaw('assigned_technician_id')
            ->selectRaw('COUNT(*) as resolved_tickets')
            ->whereNotNull('assigned_technician_id')
            ->whereIn('status', [
                TicketStatus::Resolved->value,
                TicketStatus::Closed->value,
            ])
            ->whereBetween('updated_at', [$currentStart, $currentEnd]);

        if ($scope->visibleRouterIds() !== null) {
            $this->applyServiceRouterScope($ticketSummary, $scope);
        }

        $limit = max(3, (int) config('dashboard.technician_ranking_limit', 10));

        $technicians = Technician::query()
            ->where('is_active', true)
            ->leftJoinSub(
                $workOrderSummary->groupBy('assigned_technician_id'),
                'work_order_stats',
                fn ($join) => $join->on('work_order_stats.assigned_technician_id', '=', 'technicians.id')
            )
            ->leftJoinSub(
                $ticketSummary->groupBy('assigned_technician_id'),
                'ticket_stats',
                fn ($join) => $join->on('ticket_stats.assigned_technician_id', '=', 'technicians.id')
            )
            ->select([
                'technicians.id',
                'technicians.technician_code',
                'technicians.full_name',
            ])
            ->selectRaw('COALESCE(work_order_stats.completed_installations, 0) as completed_installations')
            ->selectRaw('COALESCE(work_order_stats.completed_work_orders, 0) as completed_work_orders')
            ->selectRaw('COALESCE(ticket_stats.resolved_tickets, 0) as resolved_tickets')
            ->orderByDesc('completed_installations')
            ->orderByDesc('completed_work_orders')
            ->orderByDesc('resolved_tickets')
            ->orderBy('technicians.full_name')
            ->limit($limit)
            ->get()
            ->values()
            ->map(function (Technician $technician, int $index): array {
                return [
                    'rank' => $index + 1,
                    'id' => $technician->id,
                    'technician_code' => $technician->technician_code,
                    'full_name' => $technician->full_name,
                    'completed_installations' => (int) $technician->completed_installations,
                    'completed_work_orders' => (int) $technician->completed_work_orders,
                    'resolved_tickets' => (int) $technician->resolved_tickets,
                ];
            })
            ->all();

        return [
            'period' => $currentPeriod,
            'sort_rule' => [
                'completed_installations',
                'completed_work_orders',
                'resolved_tickets',
            ],
            'items' => $technicians,
        ];
    }
}
