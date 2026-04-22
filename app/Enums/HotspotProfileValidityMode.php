<?php

namespace App\Enums;

enum HotspotProfileValidityMode: string
{
    case DaysAfterFirstLogin = 'days_after_first_login';
    case Unlimited = 'unlimited';

    public static function values(): array
    {
        return array_map(static fn (self $mode): string => $mode->value, self::cases());
    }
}
