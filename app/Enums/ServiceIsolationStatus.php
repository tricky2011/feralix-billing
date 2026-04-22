<?php

namespace App\Enums;

enum ServiceIsolationStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Released = 'released';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public static function openValues(): array
    {
        return [
            self::Pending->value,
            self::Applied->value,
        ];
    }
}
