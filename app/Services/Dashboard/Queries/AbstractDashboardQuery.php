<?php

namespace App\Services\Dashboard\Queries;

use App\Data\Dashboard\DashboardScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

abstract class AbstractDashboardQuery
{
    protected function applyRouterScope(Builder $query, DashboardScope $scope, string $qualifiedColumn = 'router_id'): Builder
    {
        $routerIds = $scope->visibleRouterIds();

        if ($routerIds === null) {
            return $query;
        }

        if ($routerIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($qualifiedColumn, $routerIds);
    }

    protected function applyServiceRouterScope(
        Builder $query,
        DashboardScope $scope,
        string $relation = 'service',
        string $qualifiedColumn = 'router_id',
    ): Builder {
        $routerIds = $scope->visibleRouterIds();

        if ($routerIds === null) {
            return $query;
        }

        if ($routerIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas($relation, function (Builder $builder) use ($routerIds, $qualifiedColumn): void {
            $builder->whereIn($qualifiedColumn, $routerIds);
        });
    }

    protected function formatMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    protected function percentage(int|float $numerator, int|float $denominator, int $precision = 2): ?float
    {
        if ((float) $denominator <= 0.0) {
            return null;
        }

        return round(((float) $numerator / (float) $denominator) * 100, $precision);
    }

    protected function monthWindow(): array
    {
        $now = CarbonImmutable::now();

        return [
            'current_period' => $now->format('Y-m'),
            'current_start' => $now->startOfMonth(),
            'current_end' => $now->endOfMonth(),
            'previous_period' => $now->subMonthNoOverflow()->format('Y-m'),
            'previous_start' => $now->subMonthNoOverflow()->startOfMonth(),
            'previous_end' => $now->subMonthNoOverflow()->endOfMonth(),
        ];
    }

    protected function quoteSqlString(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    /**
     * @param  list<string>  $values
     */
    protected function quoteSqlList(array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => $this->quoteSqlString($value), $values));
    }
}
