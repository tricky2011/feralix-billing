<?php

namespace App\Enums;

enum ServiceAccessMode: string
{
    case Vlan = 'vlan';
    case Pppoe = 'pppoe';
    case Static = 'static';
    case Hotspot = 'hotspot';

    public function supportsPppoeIsolation(): bool
    {
        return $this === self::Pppoe;
    }

    public function supportsStaticIsolation(): bool
    {
        return $this === self::Static;
    }

    public static function values(): array
    {
        return array_map(static fn (self $mode): string => $mode->value, self::cases());
    }
}
