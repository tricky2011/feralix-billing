<?php

namespace App\Services\MasterData;

use App\Models\Location;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LocationService
{
    public function __construct(
        private readonly RoleRouterScopeService $routerScope,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $query = Location::query()
            ->withCount(['customers', 'olts'])
            ->search($filters['search'] ?? null)
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', (bool) $filters['is_active'])
            );

        $this->routerScope->applyNetworkLocationScopeViaOlt($query);

        return $query
            ->orderBy('location_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $payload): Location
    {
        return Location::query()->create($payload);
    }

    public function update(Location $location, array $payload): Location
    {
        $location->update($payload);

        return $location->refresh();
    }

    public function delete(Location $location): void
    {
        $location->delete();
    }
}
