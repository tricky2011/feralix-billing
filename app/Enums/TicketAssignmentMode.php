<?php

namespace App\Enums;

enum TicketAssignmentMode: string
{
    case Auto = 'auto';
    case Manual = 'manual';

    public static function values(): array
    {
        return array_map(static fn (self $mode): string => $mode->value, self::cases());
    }
}
