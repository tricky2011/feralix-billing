<?php

return [
    'cache_ttl_seconds' => (int) env('DASHBOARD_CACHE_TTL_SECONDS', 300),
    'revenue_chart_months' => (int) env('DASHBOARD_REVENUE_CHART_MONTHS', 12),
    'ppp_trend_days' => (int) env('DASHBOARD_PPP_TREND_DAYS', 14),
    'technician_ranking_limit' => (int) env('DASHBOARD_TECHNICIAN_RANKING_LIMIT', 10),
];
