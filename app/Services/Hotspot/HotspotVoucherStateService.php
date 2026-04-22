<?php

namespace App\Services\Hotspot;

use App\Enums\HotspotRadiusRejectReason;
use App\Enums\HotspotVoucherStatus;
use App\Exceptions\Hotspot\HotspotVoucherAccessDeniedException;
use App\Models\HotspotVoucher;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class HotspotVoucherStateService
{
    public function loadForMutation(HotspotVoucher|int $hotspotVoucher): HotspotVoucher
    {
        $voucherId = $hotspotVoucher instanceof HotspotVoucher
            ? $hotspotVoucher->id
            : $hotspotVoucher;

        return HotspotVoucher::query()
            ->with('hotspotProfile')
            ->lockForUpdate()
            ->findOrFail($voucherId);
    }

    public function prepareForAccess(
        HotspotVoucher $hotspotVoucher,
        string $macAddress,
        DateTimeInterface $accessAt,
    ): HotspotVoucher {
        $hotspotVoucher->loadMissing('hotspotProfile');

        $normalizedMacAddress = $this->normalizeMacAddress($macAddress);
        $accessedAt = Carbon::instance($accessAt);

        $this->assertAccessAllowed($hotspotVoucher, $normalizedMacAddress, $accessedAt);

        $updates = [];

        if ($hotspotVoucher->first_login_at === null) {
            $updates['first_login_at'] = $accessedAt;
            $updates['expires_at'] = $hotspotVoucher->hotspotProfile?->resolveExpiryFromFirstLogin($accessedAt);
            $updates['status'] = HotspotVoucherStatus::Active->value;

            if ($hotspotVoucher->hotspotProfile?->usesMacLock()) {
                $updates['locked_mac'] = $normalizedMacAddress;
            }
        } elseif ($hotspotVoucher->hotspotProfile?->usesMacLock() && $hotspotVoucher->locked_mac === null) {
            $updates['locked_mac'] = $normalizedMacAddress;
        }

        if ($updates !== []) {
            $hotspotVoucher->fill($updates);
            $hotspotVoucher->save();
            $hotspotVoucher = $hotspotVoucher->refresh()->loadMissing('hotspotProfile');
        }

        $this->syncRuntimeStatus($hotspotVoucher, $accessedAt);
        $this->assertAccessAllowed($hotspotVoucher, $normalizedMacAddress, $accessedAt);

        return $hotspotVoucher->refresh()->loadMissing('hotspotProfile');
    }

    public function updateBytesUsed(
        HotspotVoucher $hotspotVoucher,
        int $bytesUsed,
        ?DateTimeInterface $asOf = null,
    ): HotspotVoucher {
        $normalizedBytesUsed = max(0, $bytesUsed);

        if ((int) $hotspotVoucher->bytes_used !== $normalizedBytesUsed) {
            $hotspotVoucher->update([
                'bytes_used' => $normalizedBytesUsed,
            ]);

            $hotspotVoucher = $hotspotVoucher->refresh()->loadMissing('hotspotProfile');
        }

        $this->syncRuntimeStatus($hotspotVoucher, $asOf);

        return $hotspotVoucher->refresh()->loadMissing('hotspotProfile');
    }

    public function syncRuntimeStatus(
        HotspotVoucher $hotspotVoucher,
        ?DateTimeInterface $asOf = null,
    ): HotspotVoucherStatus {
        $status = $this->resolveRuntimeStatus($hotspotVoucher, $asOf);

        if ($hotspotVoucher->status !== $status) {
            $hotspotVoucher->update([
                'status' => $status->value,
            ]);
        }

        return $status;
    }

    public function resolveAccessDenialReason(
        HotspotVoucher $hotspotVoucher,
        ?string $macAddress,
        ?DateTimeInterface $asOf = null,
    ): ?HotspotRadiusRejectReason {
        $hotspotVoucher->loadMissing('hotspotProfile');
        $profile = $hotspotVoucher->hotspotProfile;
        $normalizedMacAddress = $macAddress !== null ? $this->normalizeMacAddress($macAddress) : null;

        if ($profile === null || ! $profile->is_active) {
            return HotspotRadiusRejectReason::ProfileInactive;
        }

        if ($this->isTimeExpired($hotspotVoucher, $asOf)) {
            return HotspotRadiusRejectReason::Expired;
        }

        if ($this->isDataDepleted($hotspotVoucher)) {
            return HotspotRadiusRejectReason::Depleted;
        }

        if (
            $normalizedMacAddress !== null &&
            $profile->usesMacLock() &&
            $hotspotVoucher->first_login_at !== null &&
            $hotspotVoucher->locked_mac !== null &&
            $hotspotVoucher->locked_mac !== $normalizedMacAddress
        ) {
            return HotspotRadiusRejectReason::MacMismatch;
        }

        return null;
    }

    public function resolveRemainingSessionTimeout(
        HotspotVoucher $hotspotVoucher,
        ?DateTimeInterface $asOf = null,
    ): ?int {
        if ($hotspotVoucher->expires_at === null) {
            return null;
        }

        $resolvedAt = $asOf !== null ? Carbon::instance($asOf) : now();

        return max(0, $resolvedAt->diffInSeconds($hotspotVoucher->expires_at, false));
    }

    public function resolveRemainingBytes(HotspotVoucher $hotspotVoucher): ?int
    {
        $hotspotVoucher->loadMissing('hotspotProfile');
        $dataLimit = $hotspotVoucher->hotspotProfile?->data_limit_bytes;

        if ($dataLimit === null) {
            return null;
        }

        return max(0, (int) $dataLimit - (int) $hotspotVoucher->bytes_used);
    }

    public function normalizeMacAddress(string $macAddress): string
    {
        return strtoupper(str_replace('-', ':', trim($macAddress)));
    }

    private function resolveRuntimeStatus(
        HotspotVoucher $hotspotVoucher,
        ?DateTimeInterface $asOf = null,
    ): HotspotVoucherStatus {
        if ($this->isTimeExpired($hotspotVoucher, $asOf) || $this->isDataDepleted($hotspotVoucher)) {
            return HotspotVoucherStatus::Expired;
        }

        if ($hotspotVoucher->first_login_at !== null) {
            return HotspotVoucherStatus::Active;
        }

        return HotspotVoucherStatus::Generated;
    }

    private function isTimeExpired(HotspotVoucher $hotspotVoucher, ?DateTimeInterface $asOf = null): bool
    {
        if ($hotspotVoucher->expires_at === null) {
            return false;
        }

        $resolvedAt = $asOf !== null ? Carbon::instance($asOf) : now();

        return $hotspotVoucher->expires_at->lte($resolvedAt);
    }

    private function isDataDepleted(HotspotVoucher $hotspotVoucher): bool
    {
        $hotspotVoucher->loadMissing('hotspotProfile');
        $profile = $hotspotVoucher->hotspotProfile;

        return $profile !== null &&
            $profile->usesDataExpiry() &&
            $profile->data_limit_bytes !== null &&
            (int) $hotspotVoucher->bytes_used >= (int) $profile->data_limit_bytes;
    }

    private function assertAccessAllowed(
        HotspotVoucher $hotspotVoucher,
        string $macAddress,
        ?DateTimeInterface $asOf = null,
    ): void {
        $reason = $this->resolveAccessDenialReason($hotspotVoucher, $macAddress, $asOf);

        if ($reason !== null) {
            throw $this->accessDeniedException($reason);
        }
    }

    private function accessDeniedException(
        HotspotRadiusRejectReason $reason,
    ): HotspotVoucherAccessDeniedException {
        return match ($reason) {
            HotspotRadiusRejectReason::ProfileInactive => new HotspotVoucherAccessDeniedException(
                reason: $reason,
                message: 'The selected voucher profile is inactive.',
            ),
            HotspotRadiusRejectReason::Expired => new HotspotVoucherAccessDeniedException(
                reason: $reason,
                message: 'The selected voucher is already expired.',
            ),
            HotspotRadiusRejectReason::Depleted => new HotspotVoucherAccessDeniedException(
                reason: $reason,
                message: 'The selected voucher has exhausted its data quota.',
            ),
            HotspotRadiusRejectReason::MacMismatch => new HotspotVoucherAccessDeniedException(
                reason: $reason,
                message: 'This voucher is locked to another MAC address.',
                field: 'mac_address',
            ),
            default => new HotspotVoucherAccessDeniedException(
                reason: $reason,
                message: 'The selected voucher cannot be used.',
            ),
        };
    }
}
