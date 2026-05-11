<?php

namespace App\Services\Hotspot;

use App\Enums\ResellerStatus;
use App\Models\HotspotProfile;
use App\Models\HotspotVoucher;
use App\Models\Reseller;
use App\Models\VoucherBatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherBatchService
{
    private const CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return VoucherBatch::query()
            ->with($this->batchRelations())
            ->withCount('vouchers')
            ->search($filters['search'] ?? null)
            ->when(
                $filters['reseller_id'] ?? null,
                fn ($query, $resellerId) => $query->where('reseller_id', $resellerId)
            )
            ->when(
                $filters['hotspot_profile_id'] ?? null,
                fn ($query, $profileId) => $query->where('hotspot_profile_id', $profileId)
            )
            ->when(
                $filters['generated_from'] ?? null,
                fn ($query, $generatedFrom) => $query->whereDate('generated_at', '>=', $generatedFrom)
            )
            ->when(
                $filters['generated_to'] ?? null,
                fn ($query, $generatedTo) => $query->whereDate('generated_at', '<=', $generatedTo)
            )
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function generate(array $payload): VoucherBatch
    {
        return DB::transaction(function () use ($payload): VoucherBatch {
            $reseller = Reseller::query()
                ->lockForUpdate()
                ->findOrFail((int) $payload['reseller_id']);

            if ($reseller->status !== ResellerStatus::Active) {
                throw ValidationException::withMessages([
                    'reseller_id' => 'The selected reseller must be active.',
                ]);
            }

            $hotspotProfile = HotspotProfile::query()->findOrFail((int) $payload['hotspot_profile_id']);

            if (! $hotspotProfile->is_active) {
                throw ValidationException::withMessages([
                    'hotspot_profile_id' => 'The selected hotspot profile must be active.',
                ]);
            }

            $totalVouchers = (int) $payload['total_vouchers'];
            $totalCost = round((float) $hotspotProfile->price * $totalVouchers, 2);
            $balanceBefore = round((float) $reseller->balance, 2);

            if ($balanceBefore < $totalCost) {
                throw ValidationException::withMessages([
                    'reseller_id' => sprintf(
                        'Insufficient reseller balance. Required %.2f but available %.2f.',
                        $totalCost,
                        $balanceBefore
                    ),
                ]);
            }

            $batch = VoucherBatch::query()->create([
                'reseller_id' => $reseller->id,
                'hotspot_profile_id' => $hotspotProfile->id,
                'batch_code' => $this->generateUniqueBatchCode(),
                'total_vouchers' => $totalVouchers,
                'total_cost' => number_format($totalCost, 2, '.', ''),
                'generated_at' => now(),
                'created_by' => $payload['created_by'] ?? Auth::id(),
            ]);

            for ($index = 0; $index < $totalVouchers; $index++) {
                $voucherCode = $this->generateUniqueVoucherCode((int) ($payload['voucher_code_length'] ?? 8));
                $username = $this->resolveUsername($payload, $voucherCode);
                $password = $this->resolvePassword($payload, $username);

                HotspotVoucher::query()->create([
                    'batch_id' => $batch->id,
                    'reseller_id' => $reseller->id,
                    'hotspot_profile_id' => $hotspotProfile->id,
                    'username' => $username,
                    'password' => $password,
                    'password_plain' => $password,
                    'voucher_code' => $voucherCode,
                    'locked_mac' => null,
                    'first_login_at' => null,
                    'expires_at' => null,
                    'bytes_used' => 0,
                ]);
            }

            $reseller->update([
                'balance' => number_format($balanceBefore - $totalCost, 2, '.', ''),
            ]);

            return $this->find($batch, withVouchers: true);
        });
    }

    public function find(VoucherBatch $voucherBatch, bool $withVouchers = true): VoucherBatch
    {
        return $this->loadBatch($voucherBatch, $withVouchers);
    }

    private function loadBatch(VoucherBatch $voucherBatch, bool $withVouchers): VoucherBatch
    {
        $relations = $this->batchRelations();

        if ($withVouchers) {
            $relations['vouchers'] = fn ($query) => $query->orderBy('id');
        }

        return $voucherBatch->refresh()->load($relations)->loadCount('vouchers');
    }

    private function batchRelations(): array
    {
        return [
            'reseller:id,reseller_code,full_name,phone,balance,status',
            'hotspotProfile:id,profile_name,validity_mode,validity_days,data_limit_bytes,price,selling_price,user_lock,expired_mode,is_active',
            'creator:id,name,email',
        ];
    }

    private function resolveUsername(array $payload, string $voucherCode): string
    {
        $usernameMode = $payload['username_mode'] ?? 'voucher_code';

        if ($usernameMode === 'prefix_random') {
            $usernamePrefix = strtoupper($payload['username_prefix'] ?? 'HS');
            $usernameLength = (int) ($payload['username_length'] ?? 10);
            $randomLength = max(1, $usernameLength - strlen($usernamePrefix));

            do {
                $username = $usernamePrefix.$this->randomCode($randomLength);
            } while (HotspotVoucher::query()->where('username', $username)->exists());

            return $username;
        }

        return $voucherCode;
    }

    private function resolvePassword(array $payload, string $username): string
    {
        $passwordMode = $payload['password_mode'] ?? 'random_secure';

        if ($passwordMode === 'same_as_username') {
            return $username;
        }

        return $this->randomCode((int) ($payload['password_length'] ?? 10));
    }

    private function generateUniqueBatchCode(): string
    {
        do {
            $batchCode = 'HB-'.now()->format('YmdHis').'-'.$this->randomCode(4);
        } while (VoucherBatch::query()->where('batch_code', $batchCode)->exists());

        return $batchCode;
    }

    private function generateUniqueVoucherCode(int $length): string
    {
        do {
            $voucherCode = $this->randomCode(max(6, min($length, 20)));
        } while (HotspotVoucher::query()->where('voucher_code', $voucherCode)->exists());

        return $voucherCode;
    }

    private function randomCode(int $length): string
    {
        $length = max(1, $length);
        $characters = self::CODE_ALPHABET;
        $lastIndex = strlen($characters) - 1;
        $generated = '';

        for ($index = 0; $index < $length; $index++) {
            $generated .= $characters[random_int(0, $lastIndex)];
        }

        return $generated;
    }
}
