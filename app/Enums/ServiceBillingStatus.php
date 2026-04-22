<?php

namespace App\Enums;

enum ServiceBillingStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Suspended = 'suspended';
    case Closed = 'closed';

    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
