<?php

namespace App\Enums;

enum ServiceRouterOperationType: string
{
    case Isolate = 'isolate';
    case Release = 'release';

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
