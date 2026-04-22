<?php

namespace App\Services\Hotspot;

use App\Exceptions\Hotspot\HotspotVoucherAccessDeniedException;
use App\Models\HotspotVoucher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HotspotVoucherService
{
    public function __construct(private readonly HotspotVoucherStateService $hotspotVoucherStateService) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return HotspotVoucher::query()
            ->with($this->voucherRelations())
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->when(
                $filters['batch_id'] ?? null,
                fn ($query, $batchId) => $query->where('batch_id', $batchId)
            )
            ->when(
                $filters['reseller_id'] ?? null,
                fn ($query, $resellerId) => $query->where('reseller_id', $resellerId)
            )
            ->when(
                $filters['hotspot_profile_id'] ?? null,
                fn ($query, $profileId) => $query->where('hotspot_profile_id', $profileId)
            )
            ->when(
                $filters['first_login_from'] ?? null,
                fn ($query, $firstLoginFrom) => $query->whereDate('first_login_at', '>=', $firstLoginFrom)
            )
            ->when(
                $filters['first_login_to'] ?? null,
                fn ($query, $firstLoginTo) => $query->whereDate('first_login_at', '<=', $firstLoginTo)
            )
            ->when(
                $filters['expires_from'] ?? null,
                fn ($query, $expiresFrom) => $query->whereDate('expires_at', '>=', $expiresFrom)
            )
            ->when(
                $filters['expires_to'] ?? null,
                fn ($query, $expiresTo) => $query->whereDate('expires_at', '<=', $expiresTo)
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(HotspotVoucher $hotspotVoucher): HotspotVoucher
    {
        $hotspotVoucher = $hotspotVoucher->refresh()->load($this->voucherRelations());
        $this->hotspotVoucherStateService->syncRuntimeStatus($hotspotVoucher);

        return $hotspotVoucher->refresh()->load($this->voucherRelations());
    }

    public function activate(HotspotVoucher $hotspotVoucher, array $payload): HotspotVoucher
    {
        return DB::transaction(function () use ($hotspotVoucher, $payload): HotspotVoucher {
            $voucher = $this->hotspotVoucherStateService->loadForMutation($hotspotVoucher);
            $macAddress = $this->normalizeMacAddress((string) $payload['mac_address']);
            $loginAt = Carbon::parse($payload['login_at'] ?? now());

            try {
                $voucher = $this->hotspotVoucherStateService->prepareForAccess($voucher, $macAddress, $loginAt);
            } catch (HotspotVoucherAccessDeniedException $exception) {
                throw ValidationException::withMessages([
                    $exception->field => $exception->getMessage(),
                ]);
            }

            return $voucher->refresh()->load(['batch', 'reseller', 'hotspotProfile']);
        });
    }

    private function voucherRelations(): array
    {
        return [
            'batch:id,batch_code,generated_at',
            'reseller:id,reseller_code,full_name',
            'hotspotProfile:id,profile_name,validity_mode,validity_days,data_limit_bytes,user_lock,expired_mode',
        ];
    }

    private function normalizeMacAddress(string $macAddress): string
    {
        return $this->hotspotVoucherStateService->normalizeMacAddress($macAddress);
    }
}
