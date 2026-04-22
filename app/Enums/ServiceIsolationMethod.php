<?php

namespace App\Enums;

enum ServiceIsolationMethod: string
{
    case AddressList = 'address_list';
    case FirewallFilter = 'firewall_filter';
    case PppProfile = 'ppp_profile';
    case Queue = 'queue';

    public function usesAddressList(): bool
    {
        return $this === self::AddressList;
    }

    public function usesQueue(): bool
    {
        return $this === self::Queue;
    }

    public function usesPppProfile(): bool
    {
        return $this === self::PppProfile;
    }

    public static function values(): array
    {
        return array_map(static fn (self $method): string => $method->value, self::cases());
    }
}
