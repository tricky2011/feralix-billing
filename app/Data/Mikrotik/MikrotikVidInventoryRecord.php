<?php

namespace App\Data\Mikrotik;

use App\Enums\VidStatus;

final readonly class MikrotikVidInventoryRecord
{
    public function __construct(
        public int $vid,
        public ?string $vlanName = null,
        public ?string $subnetCidr = null,
        public ?string $gatewayIp = null,
        public ?string $poolStartIp = null,
        public ?string $poolEndIp = null,
        public ?int $poolIpCount = null,
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            vid: (int) ($payload['vid'] ?? 0),
            vlanName: self::normalizeNullableString($payload['vlan_name'] ?? null),
            subnetCidr: self::normalizeNullableString($payload['subnet_cidr'] ?? null),
            gatewayIp: self::normalizeNullableString($payload['gateway_ip'] ?? null),
            poolStartIp: self::normalizeNullableString($payload['pool_start_ip'] ?? null),
            poolEndIp: self::normalizeNullableString($payload['pool_end_ip'] ?? null),
            poolIpCount: isset($payload['pool_ip_count']) && is_numeric($payload['pool_ip_count'])
                ? (int) $payload['pool_ip_count']
                : null,
        );
    }

    public function inferredStatus(): VidStatus
    {
        return $this->isComplete()
            ? VidStatus::Available
            : VidStatus::Unknown;
    }

    public function isComplete(): bool
    {
        return $this->subnetCidr !== null
            && $this->gatewayIp !== null
            && $this->poolStartIp !== null
            && $this->poolEndIp !== null;
    }

    public function poolRange(): ?string
    {
        if ($this->poolStartIp === null || $this->poolEndIp === null) {
            return null;
        }

        return sprintf('%s-%s', $this->poolStartIp, $this->poolEndIp);
    }

    public function criticalFields(): array
    {
        return [
            'vid' => $this->vid,
            'subnet_cidr' => $this->subnetCidr,
            'gateway_ip' => $this->gatewayIp,
            'pool_start_ip' => $this->poolStartIp,
            'pool_end_ip' => $this->poolEndIp,
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
