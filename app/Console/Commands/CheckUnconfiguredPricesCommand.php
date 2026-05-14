<?php

namespace App\Console\Commands;

use App\Enums\ServiceOverallStatus;
use App\Models\Service;
use Illuminate\Console\Command;

class CheckUnconfiguredPricesCommand extends Command
{
    protected $signature = 'billing:check-unconfigured-prices
                            {--router= : Filter by router ID}
                            {--status= : Filter by overall status (active, down, suspended, isolated)}
                            {--json : Output as JSON}';

    protected $description = 'List active services that have no monthly price configured';

    public function handle(): int
    {
        $routerId = $this->option('router');
        $statusFilter = $this->option('status');

        $query = Service::query()
            ->with(['customer:id,customer_code,full_name', 'package:id,package_name,monthly_price', 'router:id,router_code,router_name'])
            ->where(function ($query): void {
                $query->where(function ($q): void {
                    $q->whereNull('monthly_price')
                        ->orWhere('monthly_price', '<=', 0);
                })
                ->where(function ($q): void {
                    // No package or package also has no price
                    $q->whereNull('package_id')
                        ->orWhereHas('package', function ($pq): void {
                            $pq->where(function ($subQ): void {
                                $subQ->whereNull('monthly_price')
                                    ->orWhere('monthly_price', '<=', 0);
                            });
                        });
                });
            });

        if ($routerId !== null) {
            $query->where('router_id', (int) $routerId);
        }

        if ($statusFilter !== null) {
            $status = ServiceOverallStatus::tryFrom(strtolower($statusFilter));
            if ($status !== null) {
                $query->where('overall_status', $status->value);
            }
        } else {
            // Default: only show services that should be billed (active statuses)
            $query->whereIn('overall_status', [
                ServiceOverallStatus::Active->value,
                ServiceOverallStatus::Down->value,
                ServiceOverallStatus::Suspended->value,
                ServiceOverallStatus::Isolated->value,
            ]);
        }

        $services = $query->orderBy('service_code')->get();

        if ($services->isEmpty()) {
            $this->info('All billable services have prices configured.');
            return Command::SUCCESS;
        }

        $this->info("Found {$services->count()} service(s) without configured prices:");
        $this->newLine();

        if ($this->option('json')) {
            $this->line(json_encode($this->formatJson($services), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        $headers = ['Service Code', 'Customer', 'Router', 'Status'];
        $rows = $services->map(fn (Service $s) => [
            $s->service_code,
            $s->customer?->full_name ?? 'Unknown',
            $s->router?->router_name ?? 'Unknown',
            $s->overall_status instanceof ServiceOverallStatus ? $s->overall_status->value : $s->overall_status,
        ])->toArray();

        $this->table($headers, $rows);

        $this->newLine();
        $this->warn('These services will be skipped during invoice generation.');

        return Command::SUCCESS;
    }

    private function formatJson($services): array
    {
        return $services->map(fn (Service $s) => [
            'service_id' => $s->id,
            'service_code' => $s->service_code,
            'customer_name' => $s->customer?->full_name ?? 'Unknown',
            'router_name' => $s->router?->router_name ?? 'Unknown',
            'overall_status' => $s->overall_status instanceof ServiceOverallStatus ? $s->overall_status->value : $s->overall_status,
            'monthly_price' => $s->monthly_price,
            'package_name' => $s->package?->package_name,
            'package_monthly_price' => $s->package?->monthly_price,
        ])->toArray();
    }
}