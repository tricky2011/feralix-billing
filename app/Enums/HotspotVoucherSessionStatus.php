<?php

namespace App\Enums;

enum HotspotVoucherSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
