<?php

namespace App\Services\Provisioning;

use App\Enums\ServiceIsolationType;
use App\Models\Service;
use App\Models\ServiceIsolation;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class IsolirService
{
    public function __construct(
        private readonly RoleRouterScopeService $roleRouterScopeService,
        private readonly ServiceIsolationService $serviceIsolationService,
    ) {}

    public function isolir(array $payload): ServiceIsolation
    {
        $routerId = (int) $payload['router_id'];
        $service = $this->resolveServiceByUser($routerId, $payload['user_id']);

        return $this->serviceIsolationService->createIsolationRecord([
            'service_id' => $service->id,
            'router_id' => $routerId,
            'isolation_type' => ServiceIsolationType::Manual->value,
            'notes' => $payload['notes'] ?? 'Manual isolir request via admin endpoint.',
        ]);
    }

    public function release(array $payload): ServiceIsolation
    {
        $routerId = (int) $payload['router_id'];
        $service = $this->resolveServiceByUser($routerId, $payload['user_id']);

        $openIsolation = ServiceIsolation::query()
            ->where('router_id', $routerId)
            ->where('service_id', $service->id)
            ->open()
            ->latest('id')
            ->first();

        if ($openIsolation === null) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user does not have an open isolation record on the selected router.',
            ]);
        }

        return $this->serviceIsolationService->releaseIsolation($openIsolation, [
            'released_at' => now(),
            'notes' => $payload['notes'] ?? 'Manual release request via admin endpoint.',
        ]);
    }

    private function resolveServiceByUser(int $routerId, mixed $userId): Service
    {
        $this->roleRouterScopeService->ensureRouterAccessible($routerId);

        $needle = trim((string) $userId);

        if ($needle === '') {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user is invalid.',
            ]);
        }

        $needleLower = strtolower($needle);
        $query = Service::query()
            ->withTrashed()
            ->where('router_id', $routerId)
            ->where(function (Builder $builder) use ($needle, $needleLower): void {
                if (ctype_digit($needle)) {
                    $builder->whereKey((int) $needle);
                } else {
                    $builder->whereRaw('1 = 0');
                }

                $builder
                    ->orWhereRaw('LOWER(service_code) = ?', [$needleLower])
                    ->orWhereRaw('LOWER(pppoe_username) = ?', [$needleLower])
                    ->orWhereRaw('LOWER(monitor_pppoe_username) = ?', [$needleLower])
                    ->orWhereRaw('LOWER(static_ip_address) = ?', [$needleLower]);
            });

        $this->roleRouterScopeService->applyRouterScope($query, 'router_id');

        $service = $query->first();

        if ($service !== null) {
            return $service;
        }

        throw ValidationException::withMessages([
            'user_id' => 'The selected user was not found on the selected router.',
        ]);
    }
}
