<?php

namespace App\Enums;

enum InvoicePaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Issued = 'issued';
    case Overdue = 'overdue';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Canceled = 'canceled';

    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public static function payableValues(): array
    {
        return [
            self::Unpaid->value,
            self::Issued->value,
            self::Overdue->value,
            self::PartiallyPaid->value,
        ];
    }

    public static function issuedValues(): array
    {
        return [
            self::Issued->value,
            self::Overdue->value,
            self::PartiallyPaid->value,
            self::Paid->value,
            self::Canceled->value,
        ];
    }

    public function isSettled(): bool
    {
        return in_array($this, [
            self::Paid,
            self::Canceled,
        ], true);
    }
}
