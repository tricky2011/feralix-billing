<?php

namespace App\Data\Mikrotik;

use App\Models\Router;

final readonly class MikrotikApiConnectionConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public string $password,
        public float $connectTimeout,
        public float $readTimeout,
        public bool $useTls,
        public bool $verifyPeer,
        public bool $verifyPeerName,
        public bool $allowSelfSigned,
        public ?string $cafile,
        public ?string $peerName,
    ) {}

    public static function fromRouter(Router $router, array $config): self
    {
        $sslEnabled = data_get($config, 'ssl.enabled');

        return new self(
            host: trim((string) $router->mgmt_ip),
            port: (int) $router->api_port,
            username: trim((string) $router->api_username),
            password: (string) $router->api_password,
            connectTimeout: max(0.1, (float) data_get($config, 'timeouts.connect', 5.0)),
            readTimeout: max(0.1, (float) data_get($config, 'timeouts.read', 15.0)),
            useTls: $sslEnabled === null ? (int) $router->api_port === 8729 : (bool) $sslEnabled,
            verifyPeer: (bool) data_get($config, 'ssl.verify_peer', true),
            verifyPeerName: (bool) data_get($config, 'ssl.verify_peer_name', true),
            allowSelfSigned: (bool) data_get($config, 'ssl.allow_self_signed', false),
            cafile: self::normalizeNullableString(data_get($config, 'ssl.cafile')),
            peerName: self::normalizeNullableString(data_get($config, 'ssl.peer_name')),
        );
    }

    public function endpoint(): string
    {
        $scheme = $this->useTls ? 'ssl' : 'tcp';

        return sprintf('%s://%s:%d', $scheme, $this->host, $this->port);
    }

    public function streamContextOptions(): array
    {
        if (! $this->useTls) {
            return [];
        }

        return [
            'ssl' => array_filter([
                'verify_peer' => $this->verifyPeer,
                'verify_peer_name' => $this->verifyPeerName,
                'allow_self_signed' => $this->allowSelfSigned,
                'cafile' => $this->cafile,
                'peer_name' => $this->peerName,
            ], static fn (mixed $value): bool => $value !== null),
        ];
    }

    public function logContext(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'tls' => $this->useTls,
        ];
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
