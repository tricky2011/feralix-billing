<?php

namespace App\Enums;

enum ServiceNetworkStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Isolated = 'isolated';
    case Down = 'down';
    case Inactive = 'inactive';

    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
