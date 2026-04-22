<?php

namespace App\Enums;

enum CustomerType: string
{
    case Residential = 'residential';
    case Business = 'business';
    case Internal = 'internal';

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
