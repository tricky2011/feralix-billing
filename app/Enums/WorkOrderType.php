<?php

namespace App\Enums;

enum WorkOrderType: string
{
    case NewInstall = 'new_install';
    case Relocation = 'relocation';
    case Termination = 'termination';
    case OntReplacement = 'ont_replacement';
    case Other = 'other';

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
