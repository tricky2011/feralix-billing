<?php

namespace App\Enums;

enum HotspotExpiredMode: string
{
    case None = 'none';
    case Time = 'time';
    case Data = 'data';
    case TimeOrData = 'time_or_data';

    public static function values(): array
    {
        return array_map(static fn (self $mode): string => $mode->value, self::cases());
    }
}
