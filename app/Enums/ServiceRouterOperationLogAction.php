<?php

namespace App\Enums;

enum ServiceRouterOperationLogAction: string
{
    case Queued = 'queued';
    case Added = 'added';
    case AlreadyPresent = 'already_present';
    case Removed = 'removed';
    case AlreadyAbsent = 'already_absent';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_map(static fn (self $action): string => $action->value, self::cases());
    }
}
