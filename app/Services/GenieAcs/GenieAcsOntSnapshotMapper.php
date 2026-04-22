<?php

namespace App\Services\GenieAcs;

use App\Data\GenieAcs\GenieAcsOntSnapshot;
use App\Services\GenieAcs\Support\GenieAcsDeviceValueReader;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class GenieAcsOntSnapshotMapper
{
    public function __construct(private readonly GenieAcsDeviceValueReader $valueReader) {}

    public function map(array $device): GenieAcsOntSnapshot
    {
        $deviceId = $this->normalizeNullableString($this->valueReader->read($device, '_id'));

        if ($deviceId === null) {
            throw new InvalidArgumentException('GenieACS device payload is missing the [_id] field.');
        }

        $lastInformAt = $this->parseNullableDate($this->firstFieldValue($device, 'last_inform'));
        $opticalStatus = $this->normalizeNullableString($this->firstFieldValue($device, 'optical_status'));
        $opticalInfo = $this->normalizeNullableString($this->firstFieldValue($device, 'optical_info'));

        return new GenieAcsOntSnapshot(
            deviceId: $deviceId,
            serialNumber: $this->normalizeNullableString($this->firstFieldValue($device, 'serial_number')),
            status: $this->resolveStatus($this->firstFieldValue($device, 'online'), $lastInformAt),
            opticalStatus: $opticalStatus,
            opticalInfo: $opticalInfo,
            rxPower: $this->parseNullableDecimal($this->firstFieldValue($device, 'rx_power')),
            txPower: $this->parseNullableDecimal($this->firstFieldValue($device, 'tx_power')),
            ssidName: $this->normalizeNullableString($this->firstFieldValue($device, 'ssid_name')),
            wifiPassword: $this->normalizeNullableString($this->firstFieldValue($device, 'wifi_password')),
            lastInformAt: $lastInformAt,
        );
    }

    /**
     * @return list<string>
     */
    public function projection(): array
    {
        $paths = ['_id'];

        foreach ((array) config('genieacs.identifiers.serial_query_paths', []) as $path) {
            if (is_string($path) && trim($path) !== '') {
                $paths[] = trim($path);
            }
        }

        foreach ((array) config('genieacs.fields', []) as $fieldPaths) {
            foreach ((array) $fieldPaths as $path) {
                if (is_string($path) && trim($path) !== '') {
                    $paths[] = trim($path);
                }
            }
        }

        return array_values(array_unique($paths));
    }

    private function firstFieldValue(array $device, string $field): mixed
    {
        $paths = array_values(array_filter(
            (array) config("genieacs.fields.{$field}", []),
            static fn (mixed $path): bool => is_string($path) && trim($path) !== '',
        ));

        return $this->valueReader->firstValue($device, $paths);
    }

    private function resolveStatus(mixed $onlineValue, ?CarbonInterface $lastInformAt): string
    {
        $isOnline = $this->parseBooleanish($onlineValue);

        if ($isOnline !== null) {
            return $isOnline ? 'online' : 'offline';
        }

        if ($lastInformAt === null) {
            return 'unknown';
        }

        $thresholdMinutes = max(1, (int) config('genieacs.sync.online_threshold_minutes', 10));

        return $lastInformAt->greaterThanOrEqualTo(now()->subMinutes($thresholdMinutes))
            ? 'online'
            : 'offline';
    }

    private function parseBooleanish(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'online', 'up', 'connected', 'reachable' => true,
            '0', 'false', 'no', 'n', 'offline', 'down', 'disconnected', 'unreachable' => false,
            default => null,
        };
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== ''
            ? $trimmed
            : null;
    }

    private function parseNullableDecimal(mixed $value): ?float
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

    private function parseNullableDate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        $normalized = $this->normalizeNullableString($value);

        return $normalized !== null
            ? Carbon::parse($normalized)
            : null;
    }
}
