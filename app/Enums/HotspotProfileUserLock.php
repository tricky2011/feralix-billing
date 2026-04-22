<?php

namespace App\Enums;

enum HotspotProfileUserLock: string
{
    case None = 'none';
    case Mac = 'mac';

    public static function values(): array
    {
        return array_map(static fn (self $mode): string => $mode->value, self::cases());
    }
}
