<?php

namespace App\Data\GenieAcs;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final readonly class GenieAcsOntSnapshot
{
    public function __construct(
        public string $deviceId,
        public ?string $serialNumber = null,
        public string $status = 'unknown',
        public ?string $opticalStatus = null,
        public ?string $opticalInfo = null,
        public ?float $rxPower = null,
        public ?float $txPower = null,
        public ?string $ssidName = null,
        public ?string $wifiPassword = null,
        public ?CarbonInterface $lastInformAt = null,
    ) {}

    public static function fromArray(array $payload): self
    {
        $deviceId = self::normalizeNullableString($payload['device_id'] ?? null);

        if ($deviceId === null) {
            throw new InvalidArgumentException('GenieACS ONT snapshot requires a device_id value.');
        }

        return new self(
            deviceId: $deviceId,
            serialNumber: self::normalizeNullableString($payload['serial_number'] ?? null),
            status: self::normalizeStatus($payload['status'] ?? null),
            opticalStatus: self::normalizeNullableString($payload['optical_status'] ?? null),
            opticalInfo: self::normalizeNullableString($payload['optical_info'] ?? null),
            rxPower: self::parseNullableDecimal($payload['rx_power'] ?? null),
            txPower: self::parseNullableDecimal($payload['tx_power'] ?? null),
            ssidName: self::normalizeNullableString($payload['ssid_name'] ?? null),
            wifiPassword: self::normalizeNullableString($payload['wifi_password'] ?? null),
            lastInformAt: self::parseNullableDate($payload['last_inform_at'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toOntAttributes(CarbonInterface $syncedAt): array
    {
        return [
            'genieacs_device_id' => $this->deviceId,
            'status' => $this->status,
            'optical_status' => $this->opticalStatus,
            'optical_info' => $this->opticalInfo,
            'rx_power' => $this->rxPower,
            'tx_power' => $this->txPower,
            'ssid_name' => $this->ssidName,
            'wifi_password' => $this->wifiPassword,
            'last_inform_at' => $this->lastInformAt,
            'last_seen_at' => $this->lastInformAt,
            'genieacs_last_synced_at' => $syncedAt,
        ];
    }

    private static function normalizeStatus(mixed $value): string
    {
        $normalized = self::normalizeNullableString($value);

        return $normalized !== null
            ? strtolower($normalized)
            : 'unknown';
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            $trimmed = trim((string) $value);

            return $trimmed !== ''
                ? $trimmed
                : null;
        }

        return null;
    }

    private static function parseNullableDecimal(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $candidate = str_replace(',', '.', trim($value));

        if ($candidate === '') {
            return null;
        }

        if (preg_match('/[-+]?\d+(?:\.\d+)?/', $candidate, $matches) !== 1) {
            return null;
        }

        return (float) $matches[0];
    }

    private static function parseNullableDate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        $normalized = self::normalizeNullableString($value);

        return $normalized !== null
            ? Carbon::parse($normalized)
            : null;
    }
}
