<?php

namespace App\Data\Dashboard;

use App\Enums\UserRole;

final readonly class DashboardScope
{
    /**
     * @param  list<int>|null  $routerIds
     */
    public function __construct(
        public UserRole $role,
        public int $userId,
        public ?int $technicianId = null,
        public ?array $routerIds = null,
        public ?int $activeRouterId = null,
        public bool $canSwitchRouter = false,
    ) {}

    public function hasRouterRestriction(): bool
    {
        return $this->routerIds !== null;
    }

    /**
     * @return list<int>|null
     */
    public function visibleRouterIds(): ?array
    {
        return $this->routerIds;
    }

    public function usesAllRouters(): bool
    {
        return $this->routerIds === null;
    }

    public function cacheKey(): string
    {
        return sha1(json_encode([
            'role' => $this->role->value,
            'user_id' => $this->userId,
            'technician_id' => $this->technicianId,
            'router_ids' => $this->routerIds,
            'active_router_id' => $this->activeRouterId,
            'can_switch_router' => $this->canSwitchRouter,
        ], JSON_THROW_ON_ERROR));
    }
}
