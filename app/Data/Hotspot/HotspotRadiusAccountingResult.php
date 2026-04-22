<?php

namespace App\Data\Hotspot;

use App\Enums\HotspotRadiusRejectReason;

final readonly class HotspotRadiusAccountingResult
{
    public function __construct(
        public bool $processed,
        public string $action,
        public ?int $voucherId,
        public ?int $sessionRecordId,
        public ?string $acctSessionId,
        public string $username,
        public ?string $voucherStatus,
        public ?string $sessionStatus,
        public ?HotspotRadiusRejectReason $reason,
        public string $message,
        public int $bytesUsed = 0,
        public ?int $bytesRemaining = null,
        public bool $disconnectRequired = false,
        public ?string $redirectUrl = null,
        public array $radiusAttributes = [],
        public array $context = [],
    ) {}

    public function toArray(): array
    {
        return [
            'processed' => $this->processed,
            'action' => $this->action,
            'voucher_id' => $this->voucherId,
            'session_record_id' => $this->sessionRecordId,
            'acct_session_id' => $this->acctSessionId,
            'username' => $this->username,
            'voucher_status' => $this->voucherStatus,
            'session_status' => $this->sessionStatus,
            'reason' => $this->reason?->value,
            'message' => $this->message,
            'bytes_used' => $this->bytesUsed,
            'bytes_remaining' => $this->bytesRemaining,
            'disconnect_required' => $this->disconnectRequired,
            'redirect_url' => $this->redirectUrl,
            'radius_attributes' => $this->radiusAttributes,
            'context' => $this->context,
        ];
    }
}
