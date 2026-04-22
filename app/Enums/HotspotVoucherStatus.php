<?php

namespace App\Enums;

enum HotspotVoucherStatus: string
{
    case Generated = 'generated';
    case Active = 'active';
    case Expired = 'expired';

    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
