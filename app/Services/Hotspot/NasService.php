<?php

namespace App\Services\Hotspot;

use App\Models\Nas;
use App\Models\Router;
use Illuminate\Support\Facades\Cache;

class NasService
{
    private const CACHE_KEY_PREFIX = 'nas:';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get or create NAS entry for a router
     */
    public function getOrCreateForRouter(Router $router): Nas
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $router->host;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($router) {
            $defaultSecret = config('hotspot.radius.default_nas_secret', 'secret');

            return Nas::updateOrCreate(
                ['nasname' => $router->host],
                [
                    'shortname' => $router->name ?? $router->host,
                    'type' => 'mikrotik',
                    'secret' => $defaultSecret,
                    'ports' => config('hotspot.radius.nas_default_ports', 1812),
                    'description' => "Router ID: {$router->id} - {$router->name}",
                ]
            );
        });
    }

    /**
     * Update NAS secret
     */
    public function updateSecret(Nas $nas, string $secret): Nas
    {
        $nas->update(['secret' => $secret]);

        Cache::forget(self::CACHE_KEY_PREFIX . $nas->nasname);

        return $nas->fresh();
    }

    /**
     * Get all active NAS entries
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Nas>
     */
    public function getAllActive(): \Illuminate\Database\Eloquent\Collection
    {
        return Nas::query()
            ->orderBy('shortname')
            ->orderBy('nasname')
            ->get();
    }

    /**
     * Find NAS by hostname/IP
     */
    public function findByNasname(string $nasname): ?Nas
    {
        return Nas::query()->where('nasname', $nasname)->first();
    }

    /**
     * Find NAS by router ID
     */
    public function findByRouterId(int $routerId): ?Nas
    {
        $router = Router::find($routerId);

        if ($router === null) {
            return null;
        }

        return $this->findByNasname($router->host);
    }

    /**
     * Sync all routers to NAS table
     *
     * @return array{total: int, created: int, updated: int}
     */
    public function syncAllRouters(): array
    {
        $routers = Router::query()
            ->where('is_active', true)
            ->whereNotNull('host')
            ->get();

        $created = 0;
        $updated = 0;

        foreach ($routers as $router) {
            $existing = Nas::query()->where('nasname', $router->host)->exists();

            $this->getOrCreateForRouter($router);

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }
        }

        return [
            'total' => $routers->count(),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Clear cache for a NAS entry
     */
    public function clearCache(Nas $nas): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . $nas->nasname);
    }

    /**
     * Clear all NAS caches
     */
    public function clearAllCaches(): void
    {
        $nasEntries = Nas::query()->pluck('nasname');

        foreach ($nasEntries as $nasname) {
            Cache::forget(self::CACHE_KEY_PREFIX . $nasname);
        }
    }
}
