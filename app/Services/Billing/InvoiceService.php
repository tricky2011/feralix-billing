<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Services\Access\RoleRouterScopeService;
use App\Services\Billing\Queries\InvoiceDetailQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    private const RELATIONS = [
        'customer:id,customer_code,full_name',
        'service:id,customer_id,package_id,router_id,service_code,billing_status,overall_status,activation_date',
        'service.package:id,package_name,monthly_price',
        'service.router:id,router_code,router_name',
    ];

    public function __construct(
        private readonly InvoiceDetailQuery $invoiceDetailQuery,
        private readonly ManualInvoiceService $manualInvoiceService,
        private readonly MonthlyInvoiceGenerationService $monthlyInvoiceGenerationService,
        private readonly ServiceBillingStatusService $serviceBillingStatusService,
        private readonly RoleRouterScopeService $roleRouterScopeService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        if (($filters['router_id'] ?? null) !== null) {
            $this->roleRouterScopeService->ensureRouterAccessible((int) $filters['router_id']);
        }

        $query = Invoice::query()
            ->with(self::RELATIONS)
            ->withSum('payments', 'amount_paid')
            ->search($filters['search'] ?? null)
            ->when(
                $filters['customer_id'] ?? null,
                fn (Builder $builder, $customerId) => $builder->where('customer_id', $customerId)
            )
            ->when(
                $filters['service_id'] ?? null,
                fn (Builder $builder, $serviceId) => $builder->where('service_id', $serviceId)
            )
            ->router(isset($filters['router_id']) ? (int) $filters['router_id'] : null)
            ->when(
                $filters['billing_period'] ?? null,
                fn (Builder $builder, $billingPeriod) => $builder->where('billing_period', $billingPeriod)
            )
            ->when(
                $filters['payment_status'] ?? null,
                fn (Builder $builder, $paymentStatus) => $builder->where('payment_status', $paymentStatus)
            )
            ->when(
                ! empty($filters['payment_statuses'] ?? []),
                fn (Builder $builder) => $builder->whereIn('payment_status', $filters['payment_statuses'])
            )
            ->when(
                ($filters['is_overdue'] ?? false) === true,
                fn (Builder $builder) => $builder->overdue()
            );

        $this->roleRouterScopeService->applyServiceRouterScope($query, 'service', 'router_id');

        return $query
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $payload): Invoice
    {
        return $this->invoiceDetailQuery->find($this->manualInvoiceService->create($payload));
    }

    public function update(Invoice $invoice, array $payload): Invoice
    {
        return $this->invoiceDetailQuery->find($this->manualInvoiceService->update($invoice, $payload));
    }

    public function delete(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $lockedInvoice = Invoice::query()
                ->with(['service', 'payments', 'serviceIsolations'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($lockedInvoice->payments()->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoices with recorded payments cannot be deleted.',
                ]);
            }

            if ($lockedInvoice->serviceIsolations()->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoices with isolation history cannot be deleted.',
                ]);
            }

            $service = $lockedInvoice->service;

            $lockedInvoice->delete();

            if ($service !== null) {
                $this->serviceBillingStatusService->sync($service);
            }
        });
    }

    public function find(Invoice $invoice): Invoice
    {
        return $this->invoiceDetailQuery->find($invoice);
    }

    public function generateMonthlyInvoices(array $payload): array
    {
        return $this->monthlyInvoiceGenerationService->generate($payload);
    }
}
