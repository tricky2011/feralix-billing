<?php

namespace App\Data\Hotspot;

use App\Enums\HotspotRadiusRejectReason;
use Illuminate\Support\Carbon;

final readonly class HotspotRadiusAuthorizeResult
{
    public function __construct(
        public bool $accepted,
        public ?int $voucherId,
        public string $username,
        public ?string $voucherStatus,
        public ?HotspotRadiusRejectReason $reason,
        public string $message,
        public ?string $lockedMac = null,
        public ?Carbon $firstLoginAt = null,
        public ?Carbon $expiresAt = null,
        public int $bytesUsed = 0,
        public ?int $bytesRemaining = null,
        public ?int $sessionTimeoutSeconds = null,
        public ?string $redirectUrl = null,
        public array $radiusAttributes = [],
        public array $context = [],
    ) {}

    public static function accept(string $username = 'unknown', ?int $voucherId = null): self
    {
        return new self(
            accepted: true,
            voucherId: $voucherId,
            username: $username,
            voucherStatus: null,
            reason: null,
            message: 'Access accepted',
        );
    }

    public static function reject(string $username, HotspotRadiusRejectReason $reason, string $message): self
    {
        return new self(
            accepted: false,
            voucherId: null,
            username: $username,
            voucherStatus: null,
            reason: $reason,
            message: $message,
        );
    }

    public function toArray(): array
    {
        return [
            'accepted' => $this->accepted,
            'voucher_id' => $this->voucherId,
            'username' => $this->username,
            'voucher_status' => $this->voucherStatus,
            'reason' => $this->reason?->value,
            'message' => $this->message,
            'locked_mac' => $this->lockedMac,
            'first_login_at' => $this->firstLoginAt?->toISOString(),
            'expires_at' => $this->expiresAt?->toISOString(),
            'bytes_used' => $this->bytesUsed,
            'bytes_remaining' => $this->bytesRemaining,
            'session_timeout_seconds' => $this->sessionTimeoutSeconds,
            'redirect_url' => $this->redirectUrl,
            'radius_attributes' => $this->radiusAttributes,
            'context' => $this->context,
        ];
    }
}
