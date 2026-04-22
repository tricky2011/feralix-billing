<?php

namespace App\Services\Dashboard;

use App\Data\Dashboard\DashboardScope;
use App\Models\User;
use App\Services\Dashboard\Queries\DashboardChartQuery;
use App\Services\Dashboard\Queries\DashboardOverviewQuery;
use App\Services\Dashboard\Queries\DashboardRevenueAnalyticsQuery;
use App\Services\Dashboard\Queries\DashboardTechnicianRankingQuery;
use App\Services\Dashboard\Queries\TechnicianDashboardQuery;
use Closure;
use Illuminate\Support\Facades\Cache;

class DashboardAnalyticsService
{
    public function __construct(
        private readonly DashboardAccessService $dashboardAccessService,
        private readonly DashboardOverviewQuery $dashboardOverviewQuery,
        private readonly DashboardChartQuery $dashboardChartQuery,
        private readonly DashboardRevenueAnalyticsQuery $dashboardRevenueAnalyticsQuery,
        private readonly DashboardTechnicianRankingQuery $dashboardTechnicianRankingQuery,
        private readonly TechnicianDashboardQuery $technicianDashboardQuery,
    ) {}

    public function adminDashboard(User $user, array $filters = []): array
    {
        $scope = $this->dashboardAccessService->resolveAdminScope(
            $user,
            $filters['router_id'] ?? null,
        );

        return $this->buildAdminDashboardPayload($user, $scope);
    }

    public function switchAdminDashboardRouter(User $user, ?int $routerId): array
    {
        $scope = $this->dashboardAccessService->switchActiveRouter($user, $routerId);

        return $this->buildAdminDashboardPayload($user->refresh(), $scope);
    }

    public function technicianDashboard(User $user): array
    {
        return $this->rememberTechnician($user, fn (): array => $this->technicianDashboardQuery->get($user));
    }

    public function technicianRedirectPayload(): array
    {
        return $this->dashboardAccessService->technicianRedirectPayload();
    }

    private function buildAdminDashboardPayload(User $user, DashboardScope $scope): array
    {
        $overview = $this->remember($scope, 'overview', fn (): array => $this->dashboardOverviewQuery->get($scope));
        $charts = $this->remember($scope, 'charts', fn (): array => $this->dashboardChartQuery->get($scope));
        $analytics = $this->remember($scope, 'analytics', fn (): array => $this->dashboardRevenueAnalyticsQuery->get($scope));
        $technicianRanking = $this->remember($scope, 'technician-ranking', fn (): array => $this->dashboardTechnicianRankingQuery->get($scope));

        return [
            'dashboard_type' => 'admin',
            'scope' => $this->dashboardAccessService->buildScopePayload($user, $scope),
            'kpis' => $overview['kpis'],
            'summaries' => $overview['summaries'],
            'charts' => $charts,
            'analytics' => $analytics,
            'technician_ranking' => $technicianRanking,
            'meta' => [
                'cache_ttl_seconds' => (int) config('dashboard.cache_ttl_seconds', 300),
            ],
        ];
    }

    private function remember(DashboardScope $scope, string $segment, Closure $callback): mixed
    {
        $cacheTtlSeconds = max(60, (int) config('dashboard.cache_ttl_seconds', 300));

        return Cache::remember(
            sprintf('dashboard:v1:%s:%s', $segment, $scope->cacheKey()),
            now()->addSeconds($cacheTtlSeconds),
            $callback,
        );
    }

    private function rememberTechnician(User $user, Closure $callback): mixed
    {
        $cacheTtlSeconds = max(60, (int) config('dashboard.cache_ttl_seconds', 300));

        return Cache::remember(
            sprintf(
                'dashboard:v1:technician:%d:%d',
                $user->id,
                $user->technician_id ?? 0,
            ),
            now()->addSeconds($cacheTtlSeconds),
            $callback,
        );
    }
}
