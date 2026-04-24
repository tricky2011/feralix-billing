<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseHealthCheckService
{
    public function __construct(
        private readonly DatabaseConnectionFailureDetector $failureDetector,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function check(?string $connectionName = null): array
    {
        $resolvedConnection = $connectionName ?: config('database.default');
        $startedAt = microtime(true);

        try {
            /** @var Connection $connection */
            $connection = DB::connection($resolvedConnection);
            $connection->getPdo();
            $connection->selectOne('SELECT 1 AS ping');

            return [
                'ok' => true,
                'connection' => $resolvedConnection,
                'driver' => $connection->getDriverName(),
                'database' => $connection->getDatabaseName(),
                'host' => $this->resolveHost($resolvedConnection),
                'version' => $this->resolveVersion($connection),
                'latency_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ];
        } catch (Throwable $throwable) {
            return [
                'ok' => false,
                ...$this->failureDetector->describe($throwable, $resolvedConnection),
            ];
        }
    }

    private function resolveHost(string $connectionName): ?string
    {
        $config = config('database.connections.'.$connectionName);

        if (! is_array($config)) {
            return null;
        }

        return $config['host'] ?? $config['unix_socket'] ?? null;
    }

    private function resolveVersion(Connection $connection): ?string
    {
        try {
            $record = $connection->selectOne('SELECT VERSION() AS version');
        } catch (Throwable) {
            return null;
        }

        if (is_object($record) && isset($record->version)) {
            return (string) $record->version;
        }

        if (is_array($record) && isset($record['version'])) {
            return (string) $record['version'];
        }

        return null;
    }
}
