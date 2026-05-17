<?php

namespace App\Services\Dashboard\Queries;

use App\Data\Dashboard\DashboardScope;
use App\Enums\InvoicePaymentStatus;
use App\Enums\MonitorPppoeStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\TicketStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\BusinessExpense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceMonitorPppoeStatus;
use App\Models\Ticket;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class DashboardOverviewQuery extends AbstractDashboardQuery
{
    public function get(DashboardScope $scope): array
    {
        $customerSummary = $this->customerSummary($scope);
        $unpaidSummary = $this->unpaidInvoiceSummary($scope);
        $income = $this->monthlyIncome($scope);
        $expense = $this->monthlyExpense($scope);
        $installationSummary = $this->installationSummary($scope);
        $ticketSummary = $this->ticketSummary($scope);
        $pppSummary = $this->pppSummary($scope);
        $unconfiguredPriceAlert = $this->unconfiguredPriceAlert($scope);

        return [
            'kpis' => [
                'total_customers' => [
                    'label' => 'Total Customer',
                    'value' => $customerSummary['total_customers'],
                    'format' => 'number',
                ],
                'customer_active' => [
                    'label' => 'Customer Aktif',
                    'value' => $customerSummary['active_customers'],
                    'format' => 'number',
                ],
                'customer_suspended' => [
                    'label' => 'Customer Suspend',
                    'value' => $customerSummary['suspended_customers'],
                    'format' => 'number',
                ],
                'invoice_unpaid' => [
                    'label' => 'Invoice Unpaid',
                    'value' => $unpaidSummary['invoice_count'],
                    'format' => 'number',
                    'meta' => [
                        'outstanding_amount' => $this->formatMoney($unpaidSummary['outstanding_amount']),
                    ],
                ],
                'monthly_income' => [
                    'label' => 'Income Bulanan',
                    'value' => $this->formatMoney($income),
                    'format' => 'currency',
                ],
                'monthly_expense' => [
                    'label' => 'Expense Bulanan',
                    'value' => $this->formatMoney($expense),
                    'format' => 'currency',
                ],
                'monthly_profit' => [
                    'label' => 'Profit Bulanan',
                    'value' => $this->formatMoney($income - $expense),
                    'format' => 'currency',
                ],
                'monthly_tickets' => [
                    'label' => 'Tiket Bulan Berjalan',
                    'value' => $ticketSummary['total'],
                    'format' => 'number',
                ],
                'ppp_active' => [
                    'label' => 'PPP Active',
                    'value' => $pppSummary['active'],
                    'format' => 'number',
                    'meta' => [
                        'total_monitored' => $pppSummary['total_monitored'],
                        'active_ratio' => $pppSummary['active_ratio'],
                    ],
                ],
            ],
            'summaries' => [
                'installations' => $installationSummary,
                'tickets' => $ticketSummary,
                'ppp' => $pppSummary,
                'unconfigured_price_alert' => $unconfiguredPriceAlert,
            ],
        ];
    }

    private function customerSummary(DashboardScope $scope): array
    {
        if ($scope->visibleRouterIds() === []) {
            return [
                'total_customers' => 0,
                'active_customers' => 0,
                'suspended_customers' => 0,
            ];
        }

        $activeStatuses = $this->quoteSqlList([
            ServiceOverallStatus::Provisioning->value,
            ServiceOverallStatus::Active->value,
            ServiceOverallStatus::Down->value,
        ]);
        $suspendedStatuses = $this->quoteSqlList([
            ServiceOverallStatus::Suspended->value,
            ServiceOverallStatus::Isolated->value,
        ]);

        $serviceStateQuery = Service::query()
            ->select('customer_id')
            ->selectRaw("MAX(CASE WHEN overall_status IN ({$activeStatuses}) THEN 1 ELSE 0 END) as has_active_service")
            ->selectRaw("MAX(CASE WHEN overall_status IN ({$suspendedStatuses}) THEN 1 ELSE 0 END) as has_suspended_service");

        $this->applyRouterScope($serviceStateQuery, $scope, 'router_id');

        $aggregates = DB::query()
            ->fromSub($serviceStateQuery->groupBy('customer_id'), 'customer_service_states')
            ->selectRaw('COUNT(*) as total_customers')
            ->selectRaw('SUM(CASE WHEN has_active_service = 1 THEN 1 ELSE 0 END) as active_customers')
            ->selectRaw('SUM(CASE WHEN has_active_service = 0 AND has_suspended_service = 1 THEN 1 ELSE 0 END) as suspended_customers')
            ->first();

        return [
            'total_customers' => (int) ($aggregates->total_customers ?? 0),
            'active_customers' => (int) ($aggregates->active_customers ?? 0),
            'suspended_customers' => (int) ($aggregates->suspended_customers ?? 0),
        ];
    }

    private function unpaidInvoiceSummary(DashboardScope $scope): array
    {
        if ($scope->visibleRouterIds() === []) {
            return [
                'invoice_count' => 0,
                'outstanding_amount' => 0,
            ];
        }

        $paymentTotals = Payment::query()
            ->selectRaw('invoice_id, COALESCE(SUM(amount_paid), 0) as paid_amount')
            ->groupBy('invoice_id');

        $query = Invoice::query()
            ->join('services', 'services.id', '=', 'invoices.service_id')
            ->leftJoinSub($paymentTotals, 'payment_totals', fn ($join) => $join->on('payment_totals.invoice_id', '=', 'invoices.id'))
            ->whereNotIn('invoices.payment_status', [
                InvoicePaymentStatus::Paid->value,
            ]);

        $this->applyRouterScope($query, $scope, 'services.router_id');

        $aggregate = $query
            ->selectRaw('COUNT(invoices.id) as invoice_count')
            ->selectRaw('COALESCE(SUM(invoices.total_amount - COALESCE(payment_totals.paid_amount, 0)), 0) as outstanding_amount')
            ->first();

        return [
            'invoice_count' => (int) ($aggregate->invoice_count ?? 0),
            'outstanding_amount' => (float) ($aggregate->outstanding_amount ?? 0),
        ];
    }

    private function monthlyIncome(DashboardScope $scope): float
    {
        if ($scope->visibleRouterIds() === []) {
            return 0.0;
        }

        ['current_start' => $currentStart, 'current_end' => $currentEnd] = $this->monthWindow();

        $query = Payment::query()
            ->join('services', 'services.id', '=', 'payments.service_id')
            ->whereBetween('payments.paid_at', [$currentStart, $currentEnd]);

        $this->applyRouterScope($query, $scope, 'services.router_id');

        return (float) $query->sum('payments.amount_paid');
    }

    private function monthlyExpense(DashboardScope $scope): float
    {
        if ($scope->visibleRouterIds() === []) {
            return 0.0;
        }

        ['current_start' => $currentStart, 'current_end' => $currentEnd] = $this->monthWindow();

        $query = BusinessExpense::query()
            ->whereBetween('expense_date', [$currentStart->toDateString(), $currentEnd->toDateString()]);

        $this->applyRouterScope($query, $scope, 'router_id');

        return (float) $query->sum('amount');
    }

    private function installationSummary(DashboardScope $scope): array
    {
        if ($scope->visibleRouterIds() === []) {
            return [
                'period' => now()->format('Y-m'),
                'total' => 0,
                'completed' => 0,
                'pending' => 0,
                'canceled' => 0,
                'completion_rate' => null,
            ];
        }

        ['current_period' => $currentPeriod, 'current_start' => $currentStart, 'current_end' => $currentEnd] = $this->monthWindow();

        $query = WorkOrder::query()
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->where('wo_type', WorkOrderType::NewInstall->value)
            ->whereBetween('created_at', [$currentStart, $currentEnd]);

        $this->applyRouterScope($query, $scope, 'router_id');

        $statusCounts = $query
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $statusCounts->sum();
        $completed = (int) ($statusCounts[WorkOrderStatus::Completed->value] ?? 0);
        $canceled = (int) ($statusCounts[WorkOrderStatus::Canceled->value] ?? 0);
        $pending = max(
            0,
            $total - $completed - $canceled
        );

        return [
            'period' => $currentPeriod,
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'canceled' => $canceled,
            'completion_rate' => $this->percentage($completed, max(1, $total - $canceled)),
        ];
    }

    private function ticketSummary(DashboardScope $scope): array
    {
        ['current_period' => $currentPeriod, 'current_start' => $currentStart, 'current_end' => $currentEnd] = $this->monthWindow();

        $query = Ticket::query()
            ->whereBetween('created_at', [$currentStart, $currentEnd]);

        if ($scope->visibleRouterIds() !== null) {
            $this->applyServiceRouterScope($query, $scope);
        }

        $openStatuses = $this->quoteSqlList([
            TicketStatus::Open->value,
            TicketStatus::Assigned->value,
            TicketStatus::InProgress->value,
        ]);
        $closedStatuses = $this->quoteSqlList([
            TicketStatus::Resolved->value,
            TicketStatus::Closed->value,
        ]);

        $aggregate = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatuses}) THEN 1 ELSE 0 END) as open_count")
            ->selectRaw("SUM(CASE WHEN status IN ({$closedStatuses}) THEN 1 ELSE 0 END) as closed_count")
            ->first();

        return [
            'period' => $currentPeriod,
            'total' => (int) ($aggregate->total ?? 0),
            'open' => (int) ($aggregate->open_count ?? 0),
            'closed' => (int) ($aggregate->closed_count ?? 0),
        ];
    }

    private function pppSummary(DashboardScope $scope): array
    {
        $query = ServiceMonitorPppoeStatus::query();

        $this->applyRouterScope($query, $scope, 'router_id');

        $aggregate = $query
            ->selectRaw('COUNT(*) as total_monitored')
            ->selectRaw("SUM(CASE WHEN status = {$this->quoteSqlString(MonitorPppoeStatus::Online->value)} THEN 1 ELSE 0 END) as active_count")
            ->selectRaw("SUM(CASE WHEN status = {$this->quoteSqlString(MonitorPppoeStatus::Offline->value)} THEN 1 ELSE 0 END) as inactive_count")
            ->selectRaw('MAX(last_synced_at) as last_synced_at')
            ->first();

        $totalMonitored = (int) ($aggregate->total_monitored ?? 0);
        $active = (int) ($aggregate->active_count ?? 0);
        $inactive = (int) ($aggregate->inactive_count ?? 0);

        return [
            'total_monitored' => $totalMonitored,
            'active' => $active,
            'inactive' => $inactive,
            'active_ratio' => $this->percentage($active, $totalMonitored),
            'last_synced_at' => $aggregate->last_synced_at,
        ];
    }

    private function unconfiguredPriceAlert(DashboardScope $scope): array
    {
        $billableStatuses = $this->quoteSqlList([
            ServiceOverallStatus::Active->value,
            ServiceOverallStatus::Down->value,
            ServiceOverallStatus::Suspended->value,
            ServiceOverallStatus::Isolated->value,
        ]);

        $query = Service::query()
            ->selectRaw('COUNT(*) as count')
            ->whereRaw("overall_status IN ({$billableStatuses})")
            ->where(function ($q): void {
                $q->where(function ($innerQ): void {
                    $innerQ->whereNull('monthly_price')
                        ->orWhere('monthly_price', '<=', 0);
                })
                ->where(function ($innerQ): void {
                    $innerQ->whereNull('package_id')
                        ->orWhereDoesntHave('package', function ($pq): void {
                            $pq->where(function ($subQ): void {
                                $subQ->whereNotNull('monthly_price')
                                    ->where('monthly_price', '>', 0);
                            });
                        });
                });
            });

        $this->applyRouterScope($query, $scope, 'router_id');

        $count = (int) ($query->count() ?? 0);

        return [
            'has_issues' => $count > 0,
            'count' => $count,
            'message' => $count > 0
                ? "{$count} service(s) aktif tidak memiliki harga monthly."
                : null,
        ];
    }
}
