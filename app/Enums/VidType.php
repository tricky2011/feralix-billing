<?php

namespace App\Enums;

enum VidType: string
{
    case Monitoring = 'monitoring';
    case CustomerInternet = 'customer_internet';

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
