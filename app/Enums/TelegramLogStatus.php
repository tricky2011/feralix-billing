<?php

namespace App\Enums;

enum TelegramLogStatus: string
{
    case Queued = 'queued';
    case Processed = 'processed';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
