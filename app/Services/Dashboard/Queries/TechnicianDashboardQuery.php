<?php

namespace App\Services\Dashboard\Queries;

use BackedEnum;
use App\Enums\TicketStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Auth\Access\AuthorizationException;

class TechnicianDashboardQuery extends AbstractDashboardQuery
{
    public function get(User $user): array
    {
        if (! $user->isTechnician() || $user->technician_id === null) {
            throw new AuthorizationException('Technician dashboard is only available for technician users.');
        }

        ['current_period' => $currentPeriod, 'current_start' => $currentStart, 'current_end' => $currentEnd] = $this->monthWindow();

        $technician = Technician::query()->findOrFail($user->technician_id);

        $ticketAggregate = Ticket::query()
            ->where('assigned_technician_id', $technician->id)
            ->selectRaw('COUNT(*) as total_assigned')
            ->selectRaw("SUM(CASE WHEN status IN ({$this->quoteSqlList([
                TicketStatus::Open->value,
                TicketStatus::Assigned->value,
                TicketStatus::InProgress->value,
            ])}) THEN 1 ELSE 0 END) as open_tickets")
            ->selectRaw("SUM(CASE WHEN status IN ({$this->quoteSqlList([
                TicketStatus::Resolved->value,
                TicketStatus::Closed->value,
            ])}) THEN 1 ELSE 0 END) as closed_tickets")
            ->selectRaw("SUM(CASE WHEN created_at BETWEEN {$this->quoteSqlString($currentStart->toDateTimeString())} AND {$this->quoteSqlString($currentEnd->toDateTimeString())} THEN 1 ELSE 0 END) as month_tickets")
            ->first();

        $workOrderAggregate = WorkOrder::query()
            ->where('assigned_technician_id', $technician->id)
            ->selectRaw('COUNT(*) as total_work_orders')
            ->selectRaw("SUM(CASE WHEN status IN ({$this->quoteSqlList([
                WorkOrderStatus::Assigned->value,
                WorkOrderStatus::InProgress->value,
            ])}) THEN 1 ELSE 0 END) as active_work_orders")
            ->selectRaw("SUM(CASE WHEN status = {$this->quoteSqlString(WorkOrderStatus::Completed->value)} AND completed_at BETWEEN {$this->quoteSqlString($currentStart->toDateTimeString())} AND {$this->quoteSqlString($currentEnd->toDateTimeString())} THEN 1 ELSE 0 END) as month_completed_work_orders")
            ->selectRaw("SUM(CASE WHEN status = {$this->quoteSqlString(WorkOrderStatus::Completed->value)} AND wo_type = {$this->quoteSqlString(WorkOrderType::NewInstall->value)} AND completed_at BETWEEN {$this->quoteSqlString($currentStart->toDateTimeString())} AND {$this->quoteSqlString($currentEnd->toDateTimeString())} THEN 1 ELSE 0 END) as month_completed_installations")
            ->first();

        $recentTickets = Ticket::query()
            ->with(['customer:id,customer_code,full_name'])
            ->where('assigned_technician_id', $technician->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(static function (Ticket $ticket): array {
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'category' => $ticket->category,
                    'status' => $ticket->status instanceof BackedEnum
                        ? $ticket->status->value
                        : (string) $ticket->status,
                    'customer' => $ticket->customer === null ? null : [
                        'id' => $ticket->customer->id,
                        'customer_code' => $ticket->customer->customer_code,
                        'full_name' => $ticket->customer->full_name,
                    ],
                    'created_at' => $ticket->created_at?->toISOString(),
                ];
            })
            ->all();

        $recentWorkOrders = WorkOrder::query()
            ->with(['customer:id,customer_code,full_name', 'router:id,router_code,router_name'])
            ->where('assigned_technician_id', $technician->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(static function (WorkOrder $workOrder): array {
                return [
                    'id' => $workOrder->id,
                    'wo_number' => $workOrder->wo_number,
                    'wo_type' => $workOrder->wo_type instanceof BackedEnum
                        ? $workOrder->wo_type->value
                        : (string) $workOrder->wo_type,
                    'status' => $workOrder->status instanceof BackedEnum
                        ? $workOrder->status->value
                        : (string) $workOrder->status,
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
                    'created_at' => $workOrder->created_at?->toISOString(),
                ];
            })
            ->all();

        return [
            'dashboard_type' => 'technician',
            'period' => $currentPeriod,
            'technician' => [
                'id' => $technician->id,
                'technician_code' => $technician->technician_code,
                'full_name' => $technician->full_name,
            ],
            'kpis' => [
                'month_tickets' => [
                    'label' => 'Tiket Bulan Ini',
                    'value' => (int) ($ticketAggregate->month_tickets ?? 0),
                    'format' => 'number',
                ],
                'open_tickets' => [
                    'label' => 'Tiket Aktif',
                    'value' => (int) ($ticketAggregate->open_tickets ?? 0),
                    'format' => 'number',
                ],
                'active_work_orders' => [
                    'label' => 'WO Aktif',
                    'value' => (int) ($workOrderAggregate->active_work_orders ?? 0),
                    'format' => 'number',
                ],
                'month_completed_work_orders' => [
                    'label' => 'WO Selesai Bulan Ini',
                    'value' => (int) ($workOrderAggregate->month_completed_work_orders ?? 0),
                    'format' => 'number',
                ],
                'month_completed_installations' => [
                    'label' => 'Instalasi Selesai',
                    'value' => (int) ($workOrderAggregate->month_completed_installations ?? 0),
                    'format' => 'number',
                ],
            ],
            'recent_tickets' => $recentTickets,
            'recent_work_orders' => $recentWorkOrders,
        ];
    }
}
