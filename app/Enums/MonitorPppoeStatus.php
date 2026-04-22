<?php

namespace App\Enums;

enum MonitorPppoeStatus: string
{
    case Online = 'online';
    case Offline = 'offline';

    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
