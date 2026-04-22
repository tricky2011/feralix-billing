<?php

namespace App\Data\Hotspot;

use App\Enums\HotspotRadiusAccountingType;
use Illuminate\Support\Carbon;

final readonly class HotspotRadiusAccountingRequest
{
    public function __construct(
        public string $username,
        public HotspotRadiusAccountingType $acctStatusType,
        public string $acctSessionId,
        public ?string $callingStationId,
        public ?string $nasIdentifier,
        public ?string $nasIpAddress,
        public ?string $calledStationId,
        public ?string $framedIpAddress,
        public Carbon $eventAt,
        public ?int $acctSessionTime = null,
        public ?int $inputOctets = null,
        public ?int $outputOctets = null,
        public ?string $terminateCause = null,
        public array $payload = [],
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            username: trim((string) ($payload['username'] ?? '')),
            acctStatusType: HotspotRadiusAccountingType::from((string) ($payload['acct_status_type'] ?? 'start')),
            acctSessionId: trim((string) ($payload['acct_session_id'] ?? '')),
            callingStationId: self::normalizeNullableMacAddress($payload['calling_station_id'] ?? null),
            nasIdentifier: self::normalizeNullableString($payload['nas_identifier'] ?? null),
            nasIpAddress: self::normalizeNullableString($payload['nas_ip_address'] ?? null),
            calledStationId: self::normalizeNullableString($payload['called_station_id'] ?? null),
            framedIpAddress: self::normalizeNullableString($payload['framed_ip_address'] ?? null),
            eventAt: Carbon::parse($payload['event_at'] ?? now()),
            acctSessionTime: self::normalizeNullableInteger($payload['acct_session_time'] ?? null),
            inputOctets: self::normalizeNullableInteger($payload['input_octets'] ?? null),
            outputOctets: self::normalizeNullableInteger($payload['output_octets'] ?? null),
            terminateCause: self::normalizeNullableString($payload['terminate_cause'] ?? null),
            payload: is_array($payload['payload'] ?? null) ? $payload['payload'] : [],
        );
    }

    public function sessionKey(): string
    {
        $networkKey = $this->nasIdentifier ?? $this->nasIpAddress ?? 'global';

        return strtolower(sprintf('%s|%s|%s', $networkKey, $this->username, $this->acctSessionId));
    }

    private static function normalizeNullableMacAddress(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return strtoupper(str_replace('-', ':', $trimmed));
    }

    private static function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
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
