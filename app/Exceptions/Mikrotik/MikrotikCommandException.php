<?php

namespace App\Exceptions\Mikrotik;

use RuntimeException;

class MikrotikCommandException extends RuntimeException
{
    public function __construct(
        public readonly string $menuPath,
        string $mikrotikMessage,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "MikroTik error pada [{$menuPath}]: {$mikrotikMessage}",
            0,
            $previous,
        );
    }
}