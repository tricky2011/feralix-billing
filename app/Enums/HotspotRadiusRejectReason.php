<?php

namespace App\Enums;

enum HotspotRadiusRejectReason: string
{
    case InvalidCredentials = 'invalid_credentials';
    case ProfileInactive = 'profile_inactive';
    case Expired = 'expired';
    case Depleted = 'depleted';
    case MacMismatch = 'mac_mismatch';
    case UnknownVoucher = 'unknown_voucher';

    public static function values(): array
    {
        return array_map(static fn (self $reason): string => $reason->value, self::cases());
    }
}
