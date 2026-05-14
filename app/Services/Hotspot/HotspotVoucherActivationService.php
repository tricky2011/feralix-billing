<?php

namespace App\Services\Hotspot;

use App\Models\HotspotVoucher;
use App\Services\Hotspot\Radius\FreeRadiusSqlProvider;
use Illuminate\Support\Facades\Log;

/**
 * Handles hotspot voucher activation and deactivation via pure RADIUS.
 *
 * Does NOT call MikroTik API — all 8 NAS routers are managed transparently
 * through FreeRADIUS which receives Access-Requests from every router.
 */
class HotspotVoucherActivationService
{
    public function __construct(
        private readonly FreeRadiusSqlProvider $radiusSql,
    ) {}

    public function activateVoucher(HotspotVoucher $voucher): void
    {
        $voucher->loadMissing(['hotspotProfile']);

        $this->radiusSql->syncVoucher($voucher);

        $voucher->status = 'active';
        $voucher->save();

        Log::info('Hotspot voucher activated', [
            'username' => $voucher->username,
            'profile' => $voucher->hotspotProfile?->profile_name,
        ]);
    }

    public function deactivateVoucher(HotspotVoucher $voucher): void
    {
        $this->radiusSql->removeVoucher($voucher->username);

        $voucher->status = 'generated';
        $voucher->save();

        Log::info('Hotspot voucher deactivated', [
            'username' => $voucher->username,
        ]);
    }
}
