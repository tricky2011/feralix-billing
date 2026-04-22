<?php

namespace App\Services\Hotspot;

use App\Enums\ResellerStatus;
use App\Models\Reseller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ResellerService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return Reseller::query()
            ->withCount(['voucherBatches', 'hotspotVouchers'])
            ->search($filters['search'] ?? null)
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->orderBy('full_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $payload): Reseller
    {
        return Reseller::query()->create([
            ...$payload,
            'balance' => $payload['balance'] ?? 0,
            'status' => $payload['status'] ?? ResellerStatus::Active->value,
        ])->loadCount(['voucherBatches', 'hotspotVouchers']);
    }

    public function update(Reseller $reseller, array $payload): Reseller
    {
        $reseller->update([
            ...$payload,
            'balance' => $payload['balance'] ?? $reseller->balance,
            'status' => $payload['status'] ?? $reseller->status->value,
        ]);

        return $reseller->refresh()->loadCount(['voucherBatches', 'hotspotVouchers']);
    }

    public function find(Reseller $reseller): Reseller
    {
        return $reseller->loadCount(['voucherBatches', 'hotspotVouchers']);
    }
}
