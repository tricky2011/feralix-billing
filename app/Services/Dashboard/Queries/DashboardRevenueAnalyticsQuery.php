<?php

namespace App\Services\Dashboard\Queries;

use App\Data\Dashboard\DashboardScope;
use App\Enums\InvoicePaymentStatus;
use App\Enums\ServiceOverallStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;

class DashboardRevenueAnalyticsQuery extends AbstractDashboardQuery
{
    public function get(DashboardScope $scope): array
    {
        [
            'current_period' => $currentPeriod,
            'current_start' => $currentStart,
            'current_end' => $currentEnd,
            'previous_period' => $previousPeriod,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
        ] = $this->monthWindow();

        $currentRevenue = $this->sumRevenue($scope, $currentStart, $currentEnd);
        $previousRevenue = $this->sumRevenue($scope, $previousStart, $previousEnd);
        $activeCustomers = $this->activeCustomerCount($scope);
        $invoiceAnalytics = $this->invoiceAnalytics($scope, $currentPeriod);

        return [
            'revenue' => [
                'current_period' => $currentPeriod,
                'current_amount' => $this->formatMoney($currentRevenue),
                'previous_period' => $previousPeriod,
                'previous_amount' => $this->formatMoney($previousRevenue),
                'growth_amount' => $this->formatMoney($currentRevenue - $previousRevenue),
                'growth_percentage' => $this->percentage($currentRevenue - $previousRevenue, $previousRevenue),
            ],
            'arpu' => [
                'period' => $currentPeriod,
                'amount' => $this->formatMoney($activeCustomers > 0 ? $currentRevenue / $activeCustomers : 0),
                'active_customer_base' => $activeCustomers,
            ],
            'revenue_by_invoice' => $invoiceAnalytics,
        ];
    }

    private function sumRevenue(DashboardScope $scope, mixed $start, mixed $end): float
    {
        if ($scope->visibleRouterIds() === []) {
            return 0.0;
        }

        $query = Payment::query()
            ->join('services', 'services.id', '=', 'payments.service_id')
            ->whereBetween('payments.paid_at', [$start, $end]);

        $this->applyRouterScope($query, $scope, 'services.router_id');

        return (float) $query->sum('payments.amount_paid');
    }

    private function activeCustomerCount(DashboardScope $scope): int
    {
        $query = Service::query()
            ->whereIn('overall_status', [
                ServiceOverallStatus::Provisioning->value,
                ServiceOverallStatus::Active->value,
                ServiceOverallStatus::Down->value,
            ])
            ->distinct();

        $this->applyRouterScope($query, $scope, 'router_id');

        return (int) $query->count('customer_id');
    }

    private function invoiceAnalytics(DashboardScope $scope, string $billingPeriod): array
    {
        if ($scope->visibleRouterIds() === []) {
            return [
                'period' => $billingPeriod,
                'invoice_count' => 0,
                'total_billed' => $this->formatMoney(0),
                'average_invoice_value' => $this->formatMoney(0),
                'paid_invoice_count' => 0,
                'open_invoice_count' => 0,
                'collection_rate' => null,
            ];
        }

        $query = Invoice::query()
            ->join('services', 'services.id', '=', 'invoices.service_id')
            ->where('billing_period', $billingPeriod);

        $this->applyRouterScope($query, $scope, 'services.router_id');

        $aggregate = $query
            ->selectRaw('COUNT(invoices.id) as invoice_count')
            ->selectRaw('COALESCE(SUM(invoices.total_amount), 0) as total_billed')
            ->selectRaw('COALESCE(AVG(invoices.total_amount), 0) as average_invoice_value')
            ->selectRaw("SUM(CASE WHEN invoices.payment_status = {$this->quoteSqlString(InvoicePaymentStatus::Paid->value)} THEN 1 ELSE 0 END) as paid_invoice_count")
            ->selectRaw("SUM(CASE WHEN invoices.payment_status IN ({$this->quoteSqlList([
                InvoicePaymentStatus::Unpaid->value,
                InvoicePaymentStatus::Issued->value,
                InvoicePaymentStatus::Overdue->value,
                InvoicePaymentStatus::PartiallyPaid->value,
            ])}) THEN 1 ELSE 0 END) as open_invoice_count")
            ->first();

        $invoiceCount = (int) ($aggregate->invoice_count ?? 0);
        $paidInvoiceCount = (int) ($aggregate->paid_invoice_count ?? 0);

        return [
            'period' => $billingPeriod,
            'invoice_count' => $invoiceCount,
            'total_billed' => $this->formatMoney($aggregate->total_billed ?? 0),
            'average_invoice_value' => $this->formatMoney($aggregate->average_invoice_value ?? 0),
            'paid_invoice_count' => $paidInvoiceCount,
            'open_invoice_count' => (int) ($aggregate->open_invoice_count ?? 0),
            'collection_rate' => $this->percentage($paidInvoiceCount, $invoiceCount),
        ];
    }
}
