<?php

namespace App\Enums;

enum HotspotRadiusAccountingType: string
{
    case Start = 'start';
    case InterimUpdate = 'interim_update';
    case Stop = 'stop';

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    public function toEventType(): HotspotRadiusEventType
    {
        return match ($this) {
            self::Start => HotspotRadiusEventType::AccountingStart,
            self::InterimUpdate => HotspotRadiusEventType::AccountingInterimUpdate,
            self::Stop => HotspotRadiusEventType::AccountingStop,
        };
    }
}
