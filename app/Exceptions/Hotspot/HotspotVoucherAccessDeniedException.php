<?php

namespace App\Exceptions\Hotspot;

use App\Enums\HotspotRadiusRejectReason;
use RuntimeException;

class HotspotVoucherAccessDeniedException extends RuntimeException
{
    public function __construct(
        public readonly HotspotRadiusRejectReason $reason,
        string $message,
        public readonly string $field = 'voucher',
    ) {
        parent::__construct($message);
    }
}
