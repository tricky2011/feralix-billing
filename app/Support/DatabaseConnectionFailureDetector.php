<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Throwable;

class DatabaseConnectionFailureDetector
{
    /**
     * @var list<string>
     */
    private const CONNECTION_ERROR_CODES = [
        '1045',
        '1049',
        '2002',
        '2006',
    ];

    /**
     * @var list<string>
     */
    private const CONNECTION_MESSAGE_PATTERNS = [
        'access denied for user',
        'connection refused',
        'could not find driver',
        'getaddrinfo',
        'no such file or directory',
        'php_network_getaddresses',
        'server has gone away',
        'sqlstate[hy000] [1045]',
        'sqlstate[hy000] [1049]',
        'sqlstate[hy000] [2002]',
        'unknown database',
    ];

    public function isConnectionFailure(Throwable $throwable): bool
    {
        $rootCause = $this->rootCause($throwable);
        $message = strtolower($rootCause->getMessage());
        $code = $this->extractErrorCode($throwable, $rootCause);

        if (in_array($code, self::CONNECTION_ERROR_CODES, true)) {
            return true;
        }

        foreach (self::CONNECTION_MESSAGE_PATTERNS as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     message: string,
     *     details: string,
     *     hint: string,
     *     reason: string,
     *     connection: string|null,
     *     exception: string
     * }
     */
    public function describe(Throwable $throwable, ?string $connectionName = null): array
    {
        $rootCause = $this->rootCause($throwable);
        $message = strtolower($rootCause->getMessage());
        $code = $this->extractErrorCode($throwable, $rootCause);

        $reason = 'connection_failed';
        $details = 'Unable to open the configured database connection.';
        $hint = 'Check DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and DB_PASSWORD.';

        if (str_contains($message, 'could not find driver')) {
            $driverPackage = match ($connectionName) {
                'pgsql' => 'php8.3-pgsql',
                'sqlite' => 'php8.3-sqlite3',
                'sqlsrv' => 'pdo_sqlsrv',
                default => 'php8.3-mysql',
            };

            $reason = 'driver_missing';
            $details = 'The required PDO database driver is not installed on this server.';
            $hint = sprintf(
                'Install the PHP database extension for the %s connection, for example: %s, then restart PHP-FPM or the CLI service.',
                $connectionName ?? 'configured',
                $driverPackage,
            );
        } elseif ($code === '1045' || str_contains($message, 'access denied for user')) {
            $reason = 'access_denied';
            $details = 'The configured database credentials were rejected by the server.';
            $hint = 'Verify DB_USERNAME and DB_PASSWORD, then confirm the database user has permission to access the target database.';
        } elseif ($code === '1049' || str_contains($message, 'unknown database')) {
            $reason = 'database_not_found';
            $details = 'The configured database name does not exist yet.';
            $hint = 'Create the database first, then run php artisan migrate --seed.';
        } elseif (
            $code === '2002'
            || str_contains($message, 'connection refused')
            || str_contains($message, 'php_network_getaddresses')
            || str_contains($message, 'getaddrinfo')
            || str_contains($message, 'no such file or directory')
        ) {
            $reason = 'host_unreachable';
            $details = 'The application could not reach the configured database host, port, or socket.';
            $hint = 'Confirm the database service is running and that DB_HOST, DB_PORT, or DB_SOCKET match the server configuration.';
        } elseif (str_contains($message, 'server has gone away')) {
            $reason = 'server_gone_away';
            $details = 'The database server closed the connection unexpectedly.';
            $hint = 'Check database server uptime, connection timeout settings, and server error logs.';
        }

        return [
            'message' => 'Database connection is unavailable.',
            'details' => $details,
            'hint' => $hint,
            'reason' => $reason,
            'connection' => $connectionName,
            'exception' => class_basename($rootCause),
        ];
    }

    private function rootCause(Throwable $throwable): Throwable
    {
        $rootCause = $throwable;

        while ($rootCause->getPrevious() instanceof Throwable) {
            $rootCause = $rootCause->getPrevious();
        }

        return $rootCause;
    }

    private function extractErrorCode(Throwable $throwable, Throwable $rootCause): string
    {
        if ($throwable instanceof QueryException) {
            $driverCode = $throwable->errorInfo[1] ?? null;

            if ($driverCode !== null) {
                return (string) $driverCode;
            }
        }

        return (string) $rootCause->getCode();
    }
}
