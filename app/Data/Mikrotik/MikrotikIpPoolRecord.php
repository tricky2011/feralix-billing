<?php

namespace App\Data\Mikrotik;

final readonly class MikrotikIpPoolRecord
{
    /**
     * @param  list<array{start_ip:string, end_ip:string}>  $ranges
     * @param  list<string>  $usedAddresses  DHCP lease addresses that are in use
     */
    public function __construct(
        public string $name,
        public array $ranges,
        public int $totalIps,
        public int $usedIps,
        public ?int $vlanId = null,
        public ?string $vlanName = null,
        public ?string $interface = null,
        public ?string $dhcpServerName = null,
        public array $usedAddresses = [],
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            name: self::normalizeString($payload['name'] ?? ''),
            ranges: self::normalizeRanges($payload['ranges'] ?? []),
            totalIps: isset($payload['total_ips']) && is_numeric($payload['total_ips']) ? (int) $payload['total_ips'] : 0,
            usedIps: isset($payload['used_ips']) && is_numeric($payload['used_ips']) ? (int) $payload['used_ips'] : 0,
            vlanId: isset($payload['vlan_id']) && is_numeric($payload['vlan_id']) ? (int) $payload['vlan_id'] : null,
            vlanName: self::normalizeNullableString($payload['vlan_name'] ?? null),
            interface: self::normalizeNullableString($payload['interface'] ?? null),
            dhcpServerName: self::normalizeNullableString($payload['dhcp_server_name'] ?? null),
            usedAddresses: self::normalizeUsedAddresses($payload['used_addresses'] ?? []),
        );
    }

    public static function fromMikrotikData(
        string $name,
        string $ranges,
        ?int $vlanId = null,
        ?string $vlanName = null,
        ?string $interface = null,
        ?string $dhcpServerName = null,
        array $usedAddresses = [],
    ): self {
        $parsedRanges = self::parsePoolRanges($ranges);
        $totalIps = array_reduce(
            $parsedRanges,
            static fn (int $carry, array $range): int => $carry + self::ipRangeSize($range['start_ip'], $range['end_ip']),
            0,
        );

        return new self(
            name: $name,
            ranges: $parsedRanges,
            totalIps: $totalIps,
            usedIps: count($usedAddresses),
            vlanId: $vlanId,
            vlanName: $vlanName,
            interface: $interface,
            dhcpServerName: $dhcpServerName,
            usedAddresses: $usedAddresses,
        );
    }

    public function freeIps(): int
    {
        return max(0, $this->totalIps - $this->usedIps);
    }

    public function usagePercentage(): float
    {
        if ($this->totalIps === 0) {
            return 0.0;
        }

        return round(($this->usedIps / $this->totalIps) * 100, 2);
    }

    public function isComplete(): bool
    {
        return $this->totalIps > 0;
    }

    public function primaryRange(): ?array
    {
        return $this->ranges[0] ?? null;
    }

    public function primaryRangeString(): ?string
    {
        $range = $this->primaryRange();

        if ($range === null) {
            return null;
        }

        return sprintf('%s-%s', $range['start_ip'], $range['end_ip']);
    }

    public function allRangesString(): ?string
    {
        if ($this->ranges === []) {
            return null;
        }

        return implode(', ', array_map(
            static fn (array $range): string => sprintf('%s-%s', $range['start_ip'], $range['end_ip']),
            $this->ranges,
        ));
    }

    public function criticalFields(): array
    {
        return [
            'name' => $this->name,
            'vlan_id' => $this->vlanId,
            'vlan_name' => $this->vlanName,
            'ranges' => $this->allRangesString(),
            'total_ips' => $this->totalIps,
            'used_ips' => $this->usedIps,
        ];
    }

    private static function normalizeString(string $value): string
    {
        return trim($value);
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function normalizeRanges(array $ranges): array
    {
        if ($ranges === []) {
            return [];
        }

        return array_filter(array_map(
            static fn ($range): ?array => is_array($range)
                ? [
                    'start_ip' => self::normalizeString($range['start_ip'] ?? ''),
                    'end_ip' => self::normalizeString($range['end_ip'] ?? ''),
                ]
                : null,
            $ranges,
        ));
    }

    private static function normalizeUsedAddresses(array $addresses): array
    {
        return array_values(array_filter(array_map(
            static fn ($address): ?string => self::normalizeNullableString($address),
            $addresses,
        )));
    }

    /**
     * @return list<array{start_ip:string, end_ip:string}>
     */
    private static function parsePoolRanges(string $rangesString): array
    {
        if ($rangesString === '') {
            return [];
        }

        $segments = array_values(array_filter(array_map('trim', explode(',', $rangesString))));
        $parsed = [];

        foreach ($segments as $segment) {
            $parts = array_map('trim', explode('-', $segment, 2));

            if (count($parts) < 1) {
                continue;
            }

            $startIp = self::normalizeNullableString($parts[0] ?? null);

            if ($startIp === null) {
                continue;
            }

            $endIp = self::normalizeNullableString($parts[1] ?? $parts[0] ?? null);

            if ($endIp === null) {
                continue;
            }

            if (! filter_var($startIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                || ! filter_var($endIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            ) {
                continue;
            }

            $parsed[] = ['start_ip' => $startIp, 'end_ip' => $endIp];
        }

        return $parsed;
    }

    private static function ipRangeSize(string $startIp, string $endIp): int
    {
        $start = ip2long($startIp);
        $end = ip2long($endIp);

        if ($start === false || $end === false || $end < $start) {
            return 0;
        }

        return (int) ($end - $start + 1);
    }
}
