<?php

namespace App\Services\Billing;

use App\Enums\ServiceOverallStatus;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class MonthlyInvoiceGenerationService
{
    private const RELATIONS = [
        'customer:id,customer_code,full_name',
        'service:id,customer_id,package_id,router_id,service_code,billing_status,overall_status,activation_date',
        'service.package:id,package_name,monthly_price',
    ];

    public function __construct(
        private readonly ManualInvoiceService $manualInvoiceService,
        private readonly RoleRouterScopeService $roleRouterScopeService,
    ) {}

    public function generate(array $payload): array
    {
        $period = Carbon::createFromFormat('Y-m', $payload['billing_period'])->startOfMonth();
        $invoiceDate = isset($payload['invoice_date'])
            ? Carbon::parse($payload['invoice_date'])->startOfDay()
            : now()->startOfDay();
        $dueDate = isset($payload['due_date'])
            ? Carbon::parse($payload['due_date'])->startOfDay()
            : $invoiceDate->copy()->addDays((int) ($payload['due_in_days'] ?? 10));

        $generated = [];
        $skipped = [];

        $services = $this->billableServicesQuery($period, $payload)->get();

        foreach ($services as $service) {
            $archivedInvoiceExists = Invoice::query()
                ->withTrashed()
                ->where('service_id', $service->id)
                ->where('billing_period', $period->format('Y-m'))
                ->exists();

            if ($archivedInvoiceExists) {
                $skipped[] = [
                    'service_id' => $service->id,
                    'service_code' => $service->service_code,
                    'reason' => 'Invoice for this billing period already exists or has been archived.',
                ];

                continue;
            }

            $invoice = $this->manualInvoiceService->create([
                'customer_id' => $service->customer_id,
                'service_id' => $service->id,
                'billing_period' => $period->format('Y-m'),
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'subtotal' => $service->package->monthly_price,
                'penalty_amount' => $payload['penalty_amount'] ?? 0,
                'issue_now' => true,
            ]);

            $generated[] = Invoice::query()
                ->with(self::RELATIONS)
                ->withSum('payments', 'amount_paid')
                ->findOrFail($invoice->id);
        }

        return [
            'billing_period' => $period->format('Y-m'),
            'invoice_date' => $invoiceDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'generated_count' => count($generated),
            'skipped_count' => count($skipped),
            'generated' => $generated,
            'skipped' => $skipped,
        ];
    }

    private function billableServicesQuery(Carbon $period, array $filters): Builder
    {
        $periodEnd = $period->copy()->endOfMonth()->toDateString();

        if (($filters['router_id'] ?? null) !== null) {
            $this->roleRouterScopeService->ensureRouterAccessible((int) $filters['router_id']);
        }

        $query = Service::query()
            ->with([
                'customer:id,customer_code,full_name',
                'package:id,package_name,monthly_price',
            ])
            ->whereIn('overall_status', [
                ServiceOverallStatus::Active->value,
                ServiceOverallStatus::Down->value,
                ServiceOverallStatus::Suspended->value,
                ServiceOverallStatus::Isolated->value,
            ])
            ->when(
                $filters['customer_id'] ?? null,
                fn (Builder $builder, $customerId) => $builder->where('customer_id', $customerId),
            )
            ->when(
                $filters['service_id'] ?? null,
                fn (Builder $builder, $serviceId) => $builder->whereKey($serviceId),
            )
            ->when(
                $filters['router_id'] ?? null,
                fn (Builder $builder, $routerId) => $builder->where('router_id', $routerId),
            )
            ->where(function (Builder $builder) use ($periodEnd): void {
                $builder
                    ->whereNull('activation_date')
                    ->orWhereDate('activation_date', '<=', $periodEnd);
            })
            ->orderBy('service_code');

        $this->roleRouterScopeService->applyRouterScope($query, 'router_id');

        return $query;
    }
}
