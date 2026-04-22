<?php

namespace App\Enums;

enum MikrotikSyncVidActionType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Unchanged = 'unchanged';
    case Conflict = 'conflict';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_map(static fn (self $action): string => $action->value, self::cases());
    }
}
