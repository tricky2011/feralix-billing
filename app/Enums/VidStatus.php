<?php

namespace App\Enums;

enum VidStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case Reserved = 'reserved';
    case Unknown = 'unknown';
    case Conflict = 'conflict';
    case Incomplete = 'incomplete';
    case Disabled = 'disabled';
    case Unregistered = 'unregistered';

    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
