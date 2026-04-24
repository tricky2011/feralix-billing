<?php

namespace App\Support\Security;

class SensitiveValueMasker
{
    public static function secret(?string $value, int $minimumLength = 8): ?string
    {
        $value = self::normalize($value);

        if ($value === null) {
            return null;
        }

        return str_repeat('*', max($minimumLength, min(16, mb_strlen($value))));
    }

    public static function identifier(?string $value, int $visibleStart = 2, int $visibleEnd = 1): ?string
    {
        $value = self::normalize($value);

        if ($value === null) {
            return null;
        }

        $length = mb_strlen($value);

        if ($length <= ($visibleStart + $visibleEnd)) {
            return self::secret($value, 4);
        }

        return mb_substr($value, 0, $visibleStart)
            . str_repeat('*', max(4, $length - $visibleStart - $visibleEnd))
            . ($visibleEnd > 0 ? mb_substr($value, -$visibleEnd) : '');
    }

    private static function normalize(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === ''
            ? null
            : $value;
    }
}
