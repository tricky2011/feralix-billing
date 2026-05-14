<?php

namespace App\Services\Billing;

use App\Enums\ServiceOverallStatus;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MonthlyInvoiceGenerationService
{
    private const RELATIONS = [
        'customer:id,customer_code,full_name,install_date',
        'service:id,customer_id,package_id,router_id,service_code,billing_status,overall_status,activation_date,monthly_price',
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

        // Due date global (jika di-override manual dari payload)
        $globalDueDate = isset($payload['due_date'])
            ? Carbon::parse($payload['due_date'])->startOfDay()
            : null;
        $globalDueInDays = (int) ($payload['due_in_days'] ?? 10);

        $generated = [];
        $skipped = [];
        $skippedNoPrice = [];

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

            $price = $this->resolveMonthlyPrice($service);

            if ($price === null) {
                $skippedNoPrice[] = [
                    'service_id' => $service->id,
                    'service_code' => $service->service_code,
                    'customer_name' => $service->customer?->full_name ?? 'Unknown',
                    'reason' => 'No monthly price configured for this service.',
                ];

                Log::warning('Skipping invoice generation: no price configured', [
                    'service_id' => $service->id,
                    'service_code' => $service->service_code,
                    'customer_name' => $service->customer?->full_name ?? 'Unknown',
                    'billing_period' => $period->format('Y-m'),
                ]);

                continue;
            }

            $invoice = $this->manualInvoiceService->create([
                'customer_id' => $service->customer_id,
                'service_id' => $service->id,
                'billing_period' => $period->format('Y-m'),
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $this->resolveDueDate($service, $period, $invoiceDate, $globalDueDate, $globalDueInDays)->toDateString(),
                'subtotal' => $price,
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
            'due_date' => $invoiceDate->copy()->addDays($globalDueInDays)->toDateString(),
            'generated_count' => count($generated),
            'skipped_count' => count($skipped),
            'skipped_no_price_count' => count($skippedNoPrice),
            'generated' => $generated,
            'skipped' => $skipped,
            'skipped_no_price' => $skippedNoPrice,
        ];
    }

    /**
     * Generate invoice untuk satu service (prepaid flow).
     */
    public function generateForService(Service $service, string $billingPeriod, string $invoiceDate, string $dueDate): array
    {
        $price = $this->resolveMonthlyPrice($service);

        if ($price === null) {
            Log::warning('Skipping invoice generation: no price configured for service', [
                'service_id' => $service->id,
                'service_code' => $service->service_code,
                'customer_id' => $service->customer_id,
            ]);

            throw new \InvalidArgumentException(
                "Cannot generate invoice: service {$service->service_code} has no monthly price configured.",
            );
        }

        $invoice = $this->manualInvoiceService->create([
            'customer_id' => $service->customer_id,
            'service_id' => $service->id,
            'billing_period' => $billingPeriod,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'subtotal' => $price,
            'penalty_amount' => 0,
            'issue_now' => true,
        ]);

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_amount' => $invoice->total_amount,
        ];
    }

    /**
     * Resolve monthly price per service.
     * Prioritas 1: monthly_price langsung di service
     * Prioritas 2: dari package
     * Fallback: null (harga tidak dikonfigurasi)
     * Menggunakan bcmath dan round() untuk konsistensi 2 desimal.
     */
    private function resolveMonthlyPrice(Service $service): ?float
    {
        // Prioritas 1: monthly_price langsung di service
        if ($service->monthly_price !== null) {
            $monthlyPrice = (float) $service->monthly_price;
            if ($monthlyPrice > 0) {
                return round($monthlyPrice, 2);
            }
        }

        // Prioritas 2: dari package
        if ($service->package !== null && $service->package->monthly_price !== null) {
            $packagePrice = (float) $service->package->monthly_price;
            if ($packagePrice > 0) {
                return round($packagePrice, 2);
            }
        }

        // Fallback: null (harga tidak dikonfigurasi)
        return null;
    }

    /**
     * Resolve due date per customer berdasarkan install_date.
     * Contoh: pasang tanggal 15 → due date tanggal 15 bulan berjalan.
     */
    private function resolveDueDate(
        Service $service,
        Carbon $period,
        Carbon $invoiceDate,
        ?Carbon $globalDueDate,
        int $globalDueInDays,
    ): Carbon {
        // Jika ada override global dari payload, gunakan itu
        if ($globalDueDate !== null) {
            return $globalDueDate;
        }

        // Ambil install_date dari service atau customer
        $installDate = $service->activation_date
            ?? $service->customer?->install_date
            ?? null;

        if ($installDate !== null) {
            $dayOfMonth = (int) $installDate->format('d');

            // Buat due date: bulan invoice + hari dari install_date
            // Clamp ke akhir bulan jika install_date day > daysInMonth bulan tersebut
            $billingMonth = $invoiceDate->copy()->startOfMonth();
            $lastDayOfBillingMonth = $billingMonth->copy()->endOfMonth();

            $rawDueDate = $billingMonth->copy()->addDays($dayOfMonth - 1);

            // Clamp ke akhir bulan jika melampaui
            $dueDate = $rawDueDate->gt($lastDayOfBillingMonth)
                ? $lastDayOfBillingMonth
                : $rawDueDate;

            // Jika due date sudah lewat invoice_date, tambah 1 bulan
            if ($dueDate->lt($invoiceDate)) {
                $dueDate->addMonth();
            }

            return $dueDate->startOfDay();
        }

        // Fallback: due_in_days dari invoice_date
        return $invoiceDate->copy()->addDays($globalDueInDays)->startOfDay();
    }

    private function billableServicesQuery(Carbon $period, array $filters): Builder
    {
        $periodEnd = $period->copy()->endOfMonth()->toDateString();

        if (($filters['router_id'] ?? null) !== null) {
            $this->roleRouterScopeService->ensureRouterAccessible((int) $filters['router_id']);
        }

        $query = Service::query()
            ->with([
                'customer:id,customer_code,full_name,install_date',
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
