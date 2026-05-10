<?php

namespace App\Services\Finance;

use App\Models\Cashflow;
use App\Models\CashflowCategory;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CashflowService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $query = Cashflow::query()
            ->with([
                'category:id,name,type',
                'user:id,name,username',
                'reference',
            ]);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(Cashflow $cashflow): Cashflow
    {
        return $cashflow->loadMissing([
            'category:id,name,type',
            'user:id,name,username',
            'reference',
        ]);
    }

    public function createIncome(array $data): Cashflow
    {
        return $this->createManualCashflow('income', $data);
    }

    public function createExpense(array $data): Cashflow
    {
        return $this->createManualCashflow('expense', $data);
    }

    public function createFromPayment(Payment $payment): Cashflow
    {
        $payment->loadMissing([
            'invoice:id,invoice_number',
        ]);

        $cashflow = Cashflow::query()->firstOrNew([
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);

        $cashflow->type = 'income';
        $cashflow->amount = $payment->amount_paid;
        $cashflow->category_id = $this->resolveSystemIncomeCategoryId();
        $cashflow->description = $payment->invoice?->invoice_number
            ? sprintf('Payment %s', $payment->invoice->invoice_number)
            : sprintf('Payment #%d', $payment->id);
        $cashflow->source = 'system';
        $cashflow->created_by = $payment->created_by;

        if ($payment->paid_at !== null) {
            $cashflow->created_at = Carbon::parse($payment->paid_at);
        }

        $cashflow->save();

        return $this->find($cashflow);
    }

    public function updateManual(Cashflow $cashflow, array $data): Cashflow
    {
        if ($cashflow->isSystemSource()) {
            throw ValidationException::withMessages([
                'source' => 'System cashflow entries cannot be updated manually.',
            ]);
        }

        $cashflow->fill([
            'type' => $data['type'],
            'amount' => $data['amount'],
            'category_id' => $data['category_id'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
        $cashflow->save();

        return $this->find($cashflow);
    }

    public function deleteManual(Cashflow $cashflow): void
    {
        if ($cashflow->isSystemSource()) {
            throw ValidationException::withMessages([
                'source' => 'System cashflow entries cannot be deleted manually.',
            ]);
        }

        $cashflow->delete();
    }

    public function summary(array $filters = []): array
    {
        $query = Cashflow::query();
        $this->applyFilters($query, $filters, includeTypeFilter: false);

        $totalIncome = (float) (clone $query)->where('type', 'income')->sum('amount');
        $totalExpense = (float) (clone $query)->where('type', 'expense')->sum('amount');

        return [
            'total_income' => number_format($totalIncome, 2, '.', ''),
            'total_expense' => number_format($totalExpense, 2, '.', ''),
            'net_balance' => number_format($totalIncome - $totalExpense, 2, '.', ''),
            'monthly' => $this->monthlySummary($filters),
        ];
    }

    /**
     * @return Collection<int, CashflowCategory>
     */
    public function categories(): Collection
    {
        return CashflowCategory::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);
    }

    private function createManualCashflow(string $type, array $data): Cashflow
    {
        $cashflow = Cashflow::query()->create([
            'type' => $type,
            'amount' => $data['amount'],
            'category_id' => $data['category_id'] ?? null,
            'description' => $data['description'] ?? null,
            'source' => 'manual',
            'created_by' => $data['created_by'] ?? null,
        ]);

        return $this->find($cashflow);
    }

    private function resolveSystemIncomeCategoryId(): ?int
    {
        return CashflowCategory::query()
            ->where('type', 'income')
            ->where('name', 'Payment')
            ->value('id');
    }

    private function applyFilters(Builder $query, array $filters, bool $includeTypeFilter = true): void
    {
        if ($includeTypeFilter && ($filters['type'] ?? null) !== null) {
            $query->where('type', $filters['type']);
        }

        if (($filters['category_id'] ?? null) !== null) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (($filters['date_from'] ?? null) !== null) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (($filters['date_to'] ?? null) !== null) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where('description', 'like', "%{$search}%");
        }

        if (($filters['router_id'] ?? null) !== null) {
            $query->where('router_id', (int) $filters['router_id']);
        }
    }

    private function monthlySummary(array $filters): array
    {
        $start = isset($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfMonth()
            : now()->subMonths(5)->startOfMonth();

        $end = isset($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->startOfMonth()
            : now()->startOfMonth();

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $query = Cashflow::query();
        $this->applyFilters($query, $filters, includeTypeFilter: false);

        $rawRows = $query
            ->get(['type', 'amount', 'created_at'])
            ->groupBy(fn (Cashflow $cashflow): string => $cashflow->created_at?->format('Y-m') ?? 'unknown');

        $rows = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $monthKey = $cursor->format('Y-m');
            $collection = $rawRows->get($monthKey, collect());

            $income = (float) $collection
                ->where('type', 'income')
                ->sum(fn (Cashflow $cashflow): float => (float) $cashflow->amount);

            $expense = (float) $collection
                ->where('type', 'expense')
                ->sum(fn (Cashflow $cashflow): float => (float) $cashflow->amount);

            $rows[] = [
                'month' => $monthKey,
                'label' => $cursor->translatedFormat('M Y'),
                'income' => number_format($income, 2, '.', ''),
                'expense' => number_format($expense, 2, '.', ''),
                'net' => number_format($income - $expense, 2, '.', ''),
            ];

            $cursor->addMonthNoOverflow();
        }

        return $rows;
    }
}
