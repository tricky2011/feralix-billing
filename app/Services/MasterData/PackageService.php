<?php

namespace App\Services\MasterData;

use App\Models\Package;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PackageService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return Package::query()
            ->search($filters['search'] ?? null)
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy('package_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $payload): Package
    {
        return Package::query()->create([
            ...$payload,
            'ip_pool_count' => $payload['ip_pool_count'] ?? 5,
            'rate_limit_mbps' => $payload['rate_limit_mbps'] ?? 10,
            'is_active' => $payload['is_active'] ?? true,
        ]);
    }

    public function update(Package $package, array $payload): Package
    {
        $package->update([
            ...$payload,
            'ip_pool_count' => $payload['ip_pool_count'] ?? $package->ip_pool_count,
            'rate_limit_mbps' => $payload['rate_limit_mbps'] ?? $package->rate_limit_mbps,
            'is_active' => $payload['is_active'] ?? $package->is_active,
        ]);

        return $package->refresh();
    }

    public function delete(Package $package): void
    {
        $package->delete();
    }
}
