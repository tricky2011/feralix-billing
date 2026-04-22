<?php

namespace App\Enums;

enum ServiceOverallStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Down = 'down';
    case Suspended = 'suspended';
    case Isolated = 'isolated';
    case Inactive = 'inactive';
    case Terminated = 'terminated';

    public function usesDedicatedResources(): bool
    {
        return in_array($this, [
            self::Provisioning,
            self::Active,
            self::Down,
            self::Suspended,
            self::Isolated,
        ], true);
    }

    public function isMonitorSensitive(): bool
    {
        return in_array($this, [
            self::Active,
            self::Suspended,
            self::Isolated,
        ], true);
    }

    public static function resourceActiveValues(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->usesDedicatedResources())
        );
    }

    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
