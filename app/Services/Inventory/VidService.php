<?php

namespace App\Services\Inventory;

use App\Enums\VidStatus;
use App\Models\Vid;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VidService
{
    private const RELATIONS = [
        'router:id,router_code,router_name',
        'routerScope:id,router_id,scope_name',
        'customer:id,customer_code,full_name',
    ];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return Vid::query()
            ->with(self::RELATIONS)
            ->search($filters['search'] ?? null)
            ->when(
                $filters['router_id'] ?? null,
                fn ($query, $routerId) => $query->where('router_id', $routerId)
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $filters['vid_type'] ?? null,
                fn ($query, $vidType) => $query->where('vid_type', $vidType)
            )
            ->orderBy('router_id')
            ->orderBy('vid')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $payload): Vid
    {
        $vid = Vid::query()->create($this->normalizePayloadForPersistence([
            ...$payload,
            'pool_ip_count' => $payload['pool_ip_count'] ?? 5,
            'rate_limit_mbps' => $payload['rate_limit_mbps'] ?? 10,
            'status' => $payload['status'] ?? VidStatus::Available->value,
        ]));

        return $vid->load(self::RELATIONS);
    }

    public function update(Vid $vid, array $payload): Vid
    {
        $vid->update($this->normalizePayloadForPersistence([
            ...$payload,
            'pool_ip_count' => $payload['pool_ip_count'] ?? $vid->pool_ip_count,
            'rate_limit_mbps' => $payload['rate_limit_mbps'] ?? $vid->rate_limit_mbps,
            'status' => $payload['status'] ?? $vid->status?->value,
        ]));

        return $vid->refresh()->load(self::RELATIONS);
    }

    public function delete(Vid $vid): void
    {
        $vid->delete();
    }

    private function normalizePayloadForPersistence(array $payload): array
    {
        $status = $payload['status'] instanceof VidStatus
            ? $payload['status']->value
            : (string) ($payload['status'] ?? '');

        if ($status !== VidStatus::Assigned->value) {
            $payload['customer_id'] = null;
            $payload['service_id'] = null;
        }

        return $payload;
    }
}
