<?php

namespace App\Data\Hotspot;

use Illuminate\Support\Carbon;

final readonly class HotspotRadiusAuthorizeRequest
{
    public function __construct(
        public string $username,
        public string $password,
        public string $callingStationId,
        public ?string $nasIdentifier,
        public ?string $nasIpAddress,
        public ?string $calledStationId,
        public Carbon $requestedAt,
        public array $context = [],
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            username: trim((string) ($payload['username'] ?? '')),
            password: (string) ($payload['password'] ?? ''),
            callingStationId: self::normalizeMacAddress((string) ($payload['calling_station_id'] ?? '')),
            nasIdentifier: self::normalizeNullableString($payload['nas_identifier'] ?? null),
            nasIpAddress: self::normalizeNullableString($payload['nas_ip_address'] ?? null),
            calledStationId: self::normalizeNullableString($payload['called_station_id'] ?? null),
            requestedAt: Carbon::parse($payload['requested_at'] ?? now()),
            context: is_array($payload['context'] ?? null) ? $payload['context'] : [],
        );
    }

    private static function normalizeMacAddress(string $macAddress): string
    {
        return strtoupper(str_replace('-', ':', trim($macAddress)));
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
