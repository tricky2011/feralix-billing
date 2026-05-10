<?php

namespace App\Services\Mikrotik\Clients;

use App\Contracts\Mikrotik\MikrotikApiClient;
use App\Data\Mikrotik\MikrotikApiConnectionConfig;
use App\Exceptions\Mikrotik\MikrotikAuthenticationException;
use App\Exceptions\Mikrotik\MikrotikCommandException;
use App\Exceptions\Mikrotik\MikrotikConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * RouterOS v7 REST API Client.
 *
 * Uses HTTP REST API (port 80/443) instead of proprietary socket API (port 8728).
 * Compatible with RouterOS 7.x only.
 *
 * API Reference: https://help.mikrotik.com/docs/display/ROS/REST+API
 */
class RestMikrotikApiClient implements MikrotikApiClient
{
    private ?PendingRequest $http = null;

    public function __construct(
        private readonly MikrotikApiConnectionConfig $config,
        private readonly LogManager $logManager,
        private readonly int $restPort = 80,
        private readonly bool $tls = false,
    ) {}

    private function baseUrl(): string
    {
        $scheme = $this->tls ? 'https' : 'http';
        $host   = $this->config->host;
        $port   = $this->restPort;

        return "{$scheme}://{$host}:{$port}/rest";
    }

    private function http(): PendingRequest
    {
        if ($this->http === null) {
            $this->http = Http::withBasicAuth(
                $this->config->username,
                $this->config->password,
            )
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->connectTimeout(10);

            if ($this->tls) {
                $this->http = $this->http->withoutVerifying();
            }
        }

        return $this->http;
    }

    private function toRestPath(string $menuPath): string
    {
        return '/'.trim($menuPath, '/');
    }

    public function print(string $menuPath, array $properties = [], array $where = []): array
    {
        $path   = $this->toRestPath($menuPath);
        $params = [];

        foreach ($where as $key => $value) {
            if ($value !== null && $value !== '') {
                $params[$key] = $value;
            }
        }

        $this->logger()->debug('Mikrotik REST print', [
            ...$this->logContext(),
            'path'   => $menuPath,
            'where'  => $where,
            'props'  => $properties,
        ]);

        $startTime = microtime(true);

        try {
            $response = $this->http()->get(
                $this->baseUrl().$path,
                $params,
            );

            if ($response->status() === 401) {
                throw new MikrotikAuthenticationException(
                    "REST API authentication failed for {$this->config->host}"
                );
            }

            if (! $response->successful()) {
                $error = $response->json('detail') ?? $response->body();
                throw new MikrotikCommandException(
                    "REST API error [{$response->status()}]: {$error}"
                );
            }

            $records = $response->json() ?? [];

            if (! is_array($records)) {
                $records = [$records];
            }

            if (isset($records['.id']) || (count($records) > 0 && ! is_array($records[0] ?? null))) {
                $records = [$records];
            }

            if (! empty($properties)) {
                $records = array_map(
                    fn (array $record): array => array_filter(
                        $record,
                        static fn ($key): bool => in_array($key, $properties, true),
                        ARRAY_FILTER_USE_KEY,
                    ),
                    $records,
                );
            }

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $this->logger()->debug('Mikrotik REST API query completed.', [
                ...$this->logContext(),
                'command'     => $menuPath.'/print',
                'duration_ms' => $duration,
                'records'     => count($records),
            ]);

            return array_values($records);

        } catch (MikrotikAuthenticationException|MikrotikCommandException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new MikrotikConnectionException(
                "REST API connection failed for {$this->config->host}: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function add(string $menuPath, array $attributes = []): void
    {
        $path = $this->toRestPath($menuPath);

        $body = array_filter(
            $attributes,
            static fn ($v) => $v !== null && $v !== '',
        );

        $this->logger()->debug('Mikrotik REST add', [
            ...$this->logContext(),
            'path'   => $menuPath,
            'params' => $this->sanitizeForLog($body),
        ]);

        try {
            $response = $this->http()->put($this->baseUrl().$path, $body);

            if ($response->status() === 401) {
                throw new MikrotikAuthenticationException("REST API auth failed");
            }

            if (! $response->successful()) {
                $error = $response->json('detail') ?? $response->body();
                throw new MikrotikCommandException("REST API add error [{$response->status()}]: {$error}");
            }

        } catch (MikrotikAuthenticationException|MikrotikCommandException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new MikrotikConnectionException("REST API add failed: {$e->getMessage()}", previous: $e);
        }
    }

    public function remove(string $menuPath, string $id): void
    {
        $path = $this->toRestPath($menuPath);

        $this->logger()->debug('Mikrotik REST remove', [
            ...$this->logContext(),
            'path' => $menuPath,
            'id'   => $id,
        ]);

        try {
            $response = $this->http()->delete("{$this->baseUrl()}{$path}/{$id}");

            if (! $response->successful() && $response->status() !== 404) {
                $error = $response->json('detail') ?? $response->body();
                throw new MikrotikCommandException("REST API remove error [{$response->status()}]: {$error}");
            }

        } catch (MikrotikCommandException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new MikrotikConnectionException("REST API remove failed: {$e->getMessage()}", previous: $e);
        }
    }

    public function set(string $menuPath, string $id, array $attributes): void
    {
        $path = $this->toRestPath($menuPath);

        $body = array_filter(
            $attributes,
            static fn ($v) => $v !== null && $v !== '',
        );

        $this->logger()->debug('Mikrotik REST set', [
            ...$this->logContext(),
            'path'   => $menuPath,
            'id'     => $id,
            'params' => $this->sanitizeForLog($body),
        ]);

        try {
            $response = $this->http()->patch("{$this->baseUrl()}{$path}/{$id}", $body);

            if (! $response->successful()) {
                $error = $response->json('detail') ?? $response->body();
                throw new MikrotikCommandException("REST API set error [{$response->status()}]: {$error}");
            }

        } catch (MikrotikCommandException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new MikrotikConnectionException("REST API set failed: {$e->getMessage()}", previous: $e);
        }
    }

    public function setWhere(string $menuPath, array $where, array $attributes): void
    {
        $items = $this->print($menuPath, ['.id'], $where);

        foreach ($items as $item) {
            $id = $item['.id'] ?? null;
            if ($id !== null) {
                $this->set($menuPath, $id, $attributes);
            }
        }
    }

    public function removeWhere(string $menuPath, array $where): void
    {
        $items = $this->print($menuPath, ['.id'], $where);

        foreach ($items as $item) {
            $id = $item['.id'] ?? null;
            if ($id !== null) {
                $this->remove($menuPath, $id);
            }
        }
    }

    public function command(string $menuPath, array $attributes = []): void
    {
        $path = $this->toRestPath($menuPath);

        $body = array_filter(
            $attributes,
            static fn ($v) => $v !== null && $v !== '',
        );

        $this->logger()->debug('Mikrotik REST command', [
            ...$this->logContext(),
            'path'   => $menuPath,
            'params' => $this->sanitizeForLog($body),
        ]);

        try {
            $response = $this->http()->post($this->baseUrl().$path, $body);

            if (! $response->successful()) {
                $error = $response->json('detail') ?? $response->body();
                throw new MikrotikCommandException("REST API command error [{$response->status()}]: {$error}");
            }

        } catch (MikrotikCommandException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new MikrotikConnectionException("REST API command failed: {$e->getMessage()}", previous: $e);
        }
    }

    public function disconnect(): void
    {
        $this->http = null;
    }

    private function logContext(): array
    {
        return [
            'host'     => $this->config->host,
            'port'     => $this->restPort,
            'username' => $this->config->username,
            'tls'      => $this->tls,
            'client'   => 'rest',
        ];
    }

    private function sanitizeForLog(array $data): array
    {
        $sensitive = ['password', 'secret', 'key', 'token'];

        return array_map(
            static fn ($key, $value): mixed => in_array(strtolower((string) $key), $sensitive, true) ? '***' : $value,
            array_keys($data),
            $data,
        );
    }

    private function logger()
    {
        return $this->logManager->channel(
            (string) (config('mikrotik.logging.channel') ?: config('logging.default', 'stack'))
        );
    }
}