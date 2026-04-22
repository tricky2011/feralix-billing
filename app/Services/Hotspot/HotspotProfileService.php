<?php

namespace App\Services\Hotspot;

use App\Enums\HotspotExpiredMode;
use App\Enums\HotspotProfileUserLock;
use App\Enums\HotspotProfileValidityMode;
use App\Models\HotspotProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HotspotProfileService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return HotspotProfile::query()
            ->search($filters['search'] ?? null)
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', (bool) $filters['is_active'])
            )
            ->when(
                $filters['validity_mode'] ?? null,
                fn ($query, $validityMode) => $query->where('validity_mode', $validityMode)
            )
            ->when(
                $filters['expired_mode'] ?? null,
                fn ($query, $expiredMode) => $query->where('expired_mode', $expiredMode)
            )
            ->orderBy('profile_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $payload): HotspotProfile
    {
        return HotspotProfile::query()->create([
            ...$payload,
            'validity_mode' => $payload['validity_mode'] ?? HotspotProfileValidityMode::DaysAfterFirstLogin->value,
            'user_lock' => $payload['user_lock'] ?? HotspotProfileUserLock::Mac->value,
            'expired_mode' => $payload['expired_mode'] ?? HotspotExpiredMode::Time->value,
            'is_active' => $payload['is_active'] ?? true,
        ]);
    }

    public function update(HotspotProfile $hotspotProfile, array $payload): HotspotProfile
    {
        $hotspotProfile->update([
            ...$payload,
            'validity_mode' => $payload['validity_mode'] ?? $hotspotProfile->validity_mode->value,
            'user_lock' => $payload['user_lock'] ?? $hotspotProfile->user_lock->value,
            'expired_mode' => $payload['expired_mode'] ?? $hotspotProfile->expired_mode->value,
            'is_active' => $payload['is_active'] ?? $hotspotProfile->is_active,
        ]);

        return $hotspotProfile->refresh();
    }
}
