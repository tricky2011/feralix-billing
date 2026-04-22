<?php

namespace App\Services\GenieAcs\Clients;

use App\Contracts\GenieAcs\GenieAcsClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Log\LogManager;
use JsonException;
use RuntimeException;
use Throwable;

class HttpGenieAcsClient implements GenieAcsClient
{
    public function __construct(
        private readonly Factory $http,
        private readonly LogManager $logManager,
    ) {}

    public function findDevices(array $query, array $projection = [], ?int $limit = null): array
    {
        $params = [
            'query' => $this->encodeQuery($query),
        ];

        if ($projection !== []) {
            $params['projection'] = implode(',', array_values(array_unique($projection)));
        }

        if ($limit !== null && $limit > 0) {
            $params['limit'] = $limit;
        }

        try {
            $response = $this->request()
                ->get('devices', $params)
                ->throw();
        } catch (Throwable $throwable) {
            $this->logger()->error('GenieACS device query failed.', [
                'base_url' => (string) config('genieacs.base_url'),
                'query' => $query,
                'projection' => $projection,
                'limit' => $limit,
                'message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        $payload = $response->json();

        if (! is_array($payload) || ! array_is_list($payload)) {
            throw new RuntimeException('GenieACS device query returned an unexpected response shape.');
        }

        return array_values(array_filter($payload, static fn (mixed $item): bool => is_array($item)));
    }

    private function request(): PendingRequest
    {
        $request = $this->http
            ->baseUrl(rtrim((string) config('genieacs.base_url', 'http://127.0.0.1:7557'), '/').'/')
            ->acceptJson()
            ->asJson()
            ->timeout((float) config('genieacs.http.timeout', 15))
            ->connectTimeout((float) config('genieacs.http.connect_timeout', 5))
            ->withOptions([
                'verify' => (bool) config('genieacs.http.verify_ssl', true),
            ]);

        $headers = array_filter(
            (array) config('genieacs.http.headers', []),
            static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '',
        );

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        $token = trim((string) config('genieacs.auth.token', ''));

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $username = trim((string) config('genieacs.auth.username', ''));
        $password = (string) config('genieacs.auth.password', '');

        if ($username !== '') {
            $request = $request->withBasicAuth($username, $password);
        }

        return $request;
    }

    private function encodeQuery(array $query): string
    {
        try {
            return json_encode($query, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode GenieACS device query payload.', previous: $exception);
        }
    }

    private function logger()
    {
        $channel = (string) (config('genieacs.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }
}
