<?php

namespace App\Services\Dashboard\Queries;

use App\Data\Dashboard\DashboardScope;
use App\Enums\MonitorPppoeStatus;
use App\Models\Payment;
use App\Models\ServiceMonitorPppoeLog;
use App\Models\ServiceMonitorPppoeStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardChartQuery extends AbstractDashboardQuery
{
    public function get(DashboardScope $scope): array
    {
        return [
            'monthly_revenue' => $this->monthlyRevenueChart($scope),
            'ppp_active_trend' => $this->pppActiveTrendChart($scope),
        ];
    }

    private function monthlyRevenueChart(DashboardScope $scope): array
    {
        $months = max(3, (int) config('dashboard.revenue_chart_months', 12));
        $startMonth = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);
        $endMonth = CarbonImmutable::now()->endOfMonth();

        $periods = collect(range(0, $months - 1))
            ->map(fn (int $offset): CarbonImmutable => $startMonth->addMonths($offset));

        $groupExpression = $this->monthGroupExpression('payments.paid_at');
        $query = Payment::query()
            ->join('services', 'services.id', '=', 'payments.service_id')
            ->whereBetween('payments.paid_at', [$startMonth, $endMonth]);

        $this->applyRouterScope($query, $scope, 'services.router_id');

        $rows = $query
            ->selectRaw("{$groupExpression} as period")
            ->selectRaw('COALESCE(SUM(payments.amount_paid), 0) as total_amount')
            ->groupByRaw($groupExpression)
            ->pluck('total_amount', 'period');

        $points = $periods->map(function (CarbonImmutable $period) use ($rows): array {
            $key = $period->format('Y-m');

            return [
                'period' => $key,
                'label' => $period->format('M Y'),
                'revenue' => $this->formatMoney($rows[$key] ?? 0),
            ];
        })->all();

        return [
            'type' => 'bar',
            'title' => 'Revenue Bulanan',
            'x_key' => 'period',
            'y_keys' => ['revenue'],
            'points' => $points,
        ];
    }

    private function pppActiveTrendChart(DashboardScope $scope): array
    {
        $days = max(7, (int) config('dashboard.ppp_trend_days', 14));
        $today = CarbonImmutable::now()->startOfDay();
        $startDate = $today->subDays($days - 1);

        $statusQuery = ServiceMonitorPppoeStatus::query();
        $this->applyRouterScope($statusQuery, $scope, 'router_id');

        $currentActive = (int) (clone $statusQuery)
            ->where('status', MonitorPppoeStatus::Online->value)
            ->count();

        $totalMonitored = (int) $statusQuery->count();

        $logQuery = ServiceMonitorPppoeLog::query()
            ->select(['previous_status', 'status', 'observed_at'])
            ->where('observed_at', '>=', $startDate->startOfDay())
            ->orderByDesc('observed_at');

        $this->applyRouterScope($logQuery, $scope, 'router_id');

        $events = $logQuery->get()->map(static function (ServiceMonitorPppoeLog $log): array {
            return [
                'previous_status' => $log->previous_status,
                'status' => $log->status,
                'observed_at' => CarbonImmutable::parse($log->observed_at),
            ];
        })->values();

        $pointer = 0;
        $reconstructedActive = $currentActive;
        $countsByDate = [];
        $datesDescending = collect(range(0, $days - 1))
            ->map(fn (int $offset): CarbonImmutable => $today->subDays($offset));

        foreach ($datesDescending as $date) {
            $boundary = $date->endOfDay();

            while ($pointer < $events->count() && $events[$pointer]['observed_at']->greaterThan($boundary)) {
                $reconstructedActive = $this->reverseTransition(
                    $reconstructedActive,
                    $events[$pointer]['previous_status'],
                    $events[$pointer]['status'],
                );
                $pointer++;
            }

            $countsByDate[$date->toDateString()] = $reconstructedActive;
        }

        ksort($countsByDate);

        $points = collect($countsByDate)
            ->map(function (int $activeCount, string $date) use ($totalMonitored): array {
                return [
                    'date' => $date,
                    'label' => CarbonImmutable::parse($date)->format('d M'),
                    'active' => max(0, $activeCount),
                    'total_monitored' => $totalMonitored,
                    'active_ratio' => $this->percentage(max(0, $activeCount), $totalMonitored),
                ];
            })
            ->values()
            ->all();

        return [
            'type' => 'line',
            'title' => 'Tren PPP Active',
            'x_key' => 'date',
            'y_keys' => ['active'],
            'points' => $points,
        ];
    }

    private function reverseTransition(
        int $activeCount,
        MonitorPppoeStatus|string|null $previousStatus,
        MonitorPppoeStatus|string $status,
    ): int {
        $previousStatus = $previousStatus instanceof MonitorPppoeStatus
            ? $previousStatus->value
            : $previousStatus;
        $status = $status instanceof MonitorPppoeStatus
            ? $status->value
            : $status;

        $wasOnline = $previousStatus === MonitorPppoeStatus::Online->value;
        $isOnline = $status === MonitorPppoeStatus::Online->value;

        if ($wasOnline === $isOnline) {
            return $activeCount;
        }

        if ($wasOnline && ! $isOnline) {
            return $activeCount + 1;
        }

        return max(0, $activeCount - 1);
    }

    private function monthGroupExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char(date_trunc('month', {$column}), 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
