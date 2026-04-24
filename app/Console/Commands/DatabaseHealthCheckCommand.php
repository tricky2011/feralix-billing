<?php

namespace App\Console\Commands;

use App\Support\DatabaseHealthCheckService;
use Illuminate\Console\Command;

class DatabaseHealthCheckCommand extends Command
{
    protected $signature = 'db:health-check
        {--connection= : Database connection name to verify}
        {--json : Render the result as JSON output}';

    protected $description = 'Check whether the configured database connection is reachable.';

    public function handle(DatabaseHealthCheckService $healthCheckService): int
    {
        $result = $healthCheckService->check(
            is_string($this->option('connection')) && trim((string) $this->option('connection')) !== ''
                ? trim((string) $this->option('connection'))
                : null,
        );

        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ($result['ok'] ?? false) === true
                ? self::SUCCESS
                : self::FAILURE;
        }

        if (($result['ok'] ?? false) === true) {
            $this->info('Database connection is healthy.');
            $this->table(
                ['Connection', 'Driver', 'Database', 'Host', 'Version', 'Latency (ms)'],
                [[
                    $result['connection'] ?? '-',
                    $result['driver'] ?? '-',
                    $result['database'] ?? '-',
                    $result['host'] ?? '-',
                    $result['version'] ?? '-',
                    $result['latency_ms'] ?? '-',
                ]],
            );

            return self::SUCCESS;
        }

        $this->error((string) ($result['message'] ?? 'Database connection check failed.'));
        $this->line(sprintf('Reason: %s', $result['reason'] ?? 'unknown'));
        $this->line(sprintf('Details: %s', $result['details'] ?? '-'));
        $this->line(sprintf('Hint: %s', $result['hint'] ?? '-'));

        if (($result['connection'] ?? null) !== null) {
            $this->line(sprintf('Connection: %s', $result['connection']));
        }

        return self::FAILURE;
    }
}
