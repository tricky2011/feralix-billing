<?php

namespace App\Enums;

enum ServiceIsolationType: string
{
    case Manual = 'manual';
    case InvoiceOverdue = 'invoice_overdue';

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
