<?php

namespace App\Enums;

enum ServiceIsolationTargetType: string
{
    case Subnet = 'subnet';
    case Pppoe = 'pppoe';
    case Static = 'static';

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
