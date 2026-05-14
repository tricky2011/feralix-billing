<?php

namespace App\Services\Billing;

use App\Enums\InvoicePaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    private const RELATIONS = [
        'customer:id,customer_code,full_name',
        'service:id,router_id,service_code,billing_status,overall_status',
        'creator:id,name,email',
    ];

    public function __construct(
        private readonly InvoicePaymentStatusService $invoicePaymentStatusService,
        private readonly CashflowIncomeService $cashflowIncomeService,
        private readonly RoleRouterScopeService $roleRouterScopeService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        if (($filters['router_id'] ?? null) !== null) {
            $this->roleRouterScopeService->ensureRouterAccessible((int) $filters['router_id']);
        }

        $query = Payment::query()
            ->with($this->paymentRelations())
            ->search($filters['search'] ?? null)
            ->when(
                $filters['invoice_id'] ?? null,
                fn (Builder $builder, $invoiceId) => $builder->where('invoice_id', $invoiceId)
            )
            ->when(
                $filters['customer_id'] ?? null,
                fn (Builder $builder, $customerId) => $builder->where('customer_id', $customerId)
            )
            ->when(
                $filters['service_id'] ?? null,
                fn (Builder $builder, $serviceId) => $builder->where('service_id', $serviceId)
            )
            ->when(
                $filters['router_id'] ?? null,
                fn (Builder $builder, $routerId) => $builder->whereHas('service', fn (Builder $serviceQuery) => $serviceQuery->where('router_id', $routerId))
            )
            ->when(
                $filters['payment_method'] ?? null,
                fn (Builder $builder, $paymentMethod) => $builder->where('payment_method', $paymentMethod)
            )
            ->when(
                $filters['paid_from'] ?? null,
                fn (Builder $builder, $paidFrom) => $builder->whereDate('paid_at', '>=', $paidFrom)
            )
            ->when(
                $filters['paid_to'] ?? null,
                fn (Builder $builder, $paidTo) => $builder->whereDate('paid_at', '<=', $paidTo)
            );

        $this->roleRouterScopeService->applyServiceRouterScope($query, 'service', 'router_id');

        return $query
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $payload): Payment
    {
        return DB::transaction(function () use ($payload): Payment {
            $invoice = Invoice::query()
                ->with(['service:id,router_id,service_code,billing_status,overall_status'])
                ->lockForUpdate()
                ->findOrFail((int) $payload['invoice_id']);

            $this->roleRouterScopeService->ensureModelAccessibleForInput($invoice, 'invoice_id');

            if ($invoice->payment_status === InvoicePaymentStatus::Canceled) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Canceled invoices cannot receive payments.',
                ]);
            }

            $amountAlreadyPaid = (float) Payment::query()
                ->where('invoice_id', $invoice->id)
                ->lockForUpdate()
                ->sum('amount_paid');

            $remainingAmount = round((float) $invoice->total_amount - $amountAlreadyPaid, 2);
            $incomingAmount = round((float) $payload['amount_paid'], 2);

            if ($remainingAmount <= 0) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'The selected invoice is already fully paid.',
                ]);
            }

            if ($incomingAmount > $remainingAmount) {
                throw ValidationException::withMessages([
                    'amount_paid' => sprintf(
                        'Payment exceeds the remaining invoice balance. Remaining amount is %.2f.',
                        $remainingAmount
                    ),
                ]);
            }

            $payment = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'service_id' => $invoice->service_id,
                'amount_paid' => number_format($incomingAmount, 2, '.', ''),
                'payment_method' => $payload['payment_method'],
                'paid_at' => $payload['paid_at'],
                'reference_no' => $payload['reference_no'] ?? null,
                'created_by' => Auth::id(),
                'notes' => $payload['notes'] ?? null,
            ]);

            $newPaidAmount = round($amountAlreadyPaid + $incomingAmount, 2);
            $invoice = $this->invoicePaymentStatusService->sync(
                $invoice,
                amountPaid: $newPaidAmount,
                referenceDate: Carbon::parse((string) $payload['paid_at']),
                issued: true,
            );
            $this->cashflowIncomeService->recordPayment($payment, $invoice->loadMissing('service'));

            return $this->loadPayment($payment);
        });
    }

    public function settleInvoice(Invoice $invoice, array $payload = []): Payment
    {
        return DB::transaction(function () use ($invoice, $payload): Payment {
            $invoice = Invoice::query()
                ->withSum('payments', 'amount_paid')
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $remainingAmount = round((float) $invoice->total_amount - (float) $invoice->amountPaid(), 2);

            if ($remainingAmount <= 0) {
                throw ValidationException::withMessages([
                    'invoice' => 'The selected invoice is already fully paid.',
                ]);
            }

            if ($invoice->payment_status === InvoicePaymentStatus::Canceled) {
                throw ValidationException::withMessages([
                    'invoice' => 'Canceled invoices cannot be settled.',
                ]);
            }

            $this->roleRouterScopeService->ensureModelAccessibleForInput($invoice, 'invoice');

            return $this->createPaymentUnderExistingTransaction(
                $invoice,
                $remainingAmount,
                $payload
            );
        });
    }

    private function createPaymentUnderExistingTransaction(
        Invoice $invoice,
        float $remainingAmount,
        array $payload
    ): Payment {
        $incomingAmount = round($remainingAmount, 2);

        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'service_id' => $invoice->service_id,
            'amount_paid' => number_format($incomingAmount, 2, '.', ''),
            'payment_method' => $payload['payment_method'] ?? 'manual_adjustment',
            'paid_at' => $payload['paid_at'] ?? now()->toDateTimeString(),
            'reference_no' => $payload['reference_no'] ?? null,
            'created_by' => $payload['created_by'] ?? Auth::id(),
            'notes' => $payload['notes'] ?? null,
        ]);

        $invoice = $this->invoicePaymentStatusService->sync(
            $invoice,
            amountPaid: $remainingAmount,
            referenceDate: Carbon::parse((string) ($payload['paid_at'] ?? now()->toDateTimeString())),
            issued: true,
        );
        $this->cashflowIncomeService->recordPayment($payment, $invoice->loadMissing('service'));

        return $this->loadPayment($payment);
    }

    public function find(Payment $payment): Payment
    {
        return $this->loadPayment($payment);
    }

    private function loadPayment(Payment $payment): Payment
    {
        return $payment->refresh()->load($this->paymentRelations());
    }

    private function paymentRelations(): array
    {
        return [
            'invoice' => fn ($query) => $query
                ->select([
                    'id',
                    'customer_id',
                    'service_id',
                    'invoice_number',
                    'billing_period',
                    'due_date',
                    'total_amount',
                    'payment_status',
                ])
                ->withSum('payments', 'amount_paid'),
            'cashflowTransaction',
            ...self::RELATIONS,
        ];
    }
}
