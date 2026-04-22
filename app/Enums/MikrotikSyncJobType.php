<?php

namespace App\Enums;

enum MikrotikSyncJobType: string
{
    case VidInventory = 'vid_inventory';

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
