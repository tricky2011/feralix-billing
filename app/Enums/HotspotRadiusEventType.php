<?php

namespace App\Enums;

enum HotspotRadiusEventType: string
{
    case AuthAccept = 'auth_accept';
    case AuthReject = 'auth_reject';
    case AccountingStart = 'accounting_start';
    case AccountingInterimUpdate = 'accounting_interim_update';
    case AccountingStop = 'accounting_stop';
    case AccountingIgnored = 'accounting_ignored';

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
