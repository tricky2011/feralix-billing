<?php

namespace App\Services\Hotspot;

use App\Models\HotspotProfile;
use App\Models\HotspotService;
use App\Models\HotspotVoucher;
use App\Models\Router;
use App\Services\Hotspot\Radius\FreeRadiusSqlProvider;
use App\Services\Mikrotik\MikrotikHotspotSecretService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Core service for centralized hotspot management.
 *
 * Business logic:
 * - 1 voucher can be used on MULTIPLE routers simultaneously (cross-router SSO)
 * - Per router, a HotspotService record tracks provisioning state
 * - FreeRADIUS is the auth backend — voucher credentials are written to radcheck/radreply
 * - MikroTik routers are the NAS — they forward RADIUS to FreeRADIUS server
 *
 * Provisioning flow:
 *   activateOnRouter(voucher, router)
 *     1. Create HotspotService record (router + voucher, status=provisioning)
 *     2. Call MikrotikHotspotSecretService.createUser() on router
 *     3. On success → mark HotspotService active, sync FreeRADIUS radcheck/radreply
 *     4. On failure → mark HotspotService failed, log error
 *
 * Removal flow:
 *   deactivateOnRouter(voucher, router)
 *     1. Call MikrotikHotspotSecretService.removeUser() on router
 *     2. Update HotspotService status to 'removed'
 *     3. FreeRADIUS entry removed via FreeRadiusSqlProvider.disableVoucher()
 *
 * Note: FreeRADIUS sync is global (not per-router) because auth is centralized.
 * Multiple NASes (routers) share the same username/password from radcheck.
 */
class CentralizedHotspotService
{
    public function __construct(
        private readonly MikrotikHotspotSecretService $mikrotikService,
        private readonly FreeRadiusSqlProvider $radiusProvider,
    ) {}

    /**
     * Activate a voucher on a specific router.
     *
     * @return array{success: bool, mikrotik_id: string|null, message: string}
     */
    public function activateOnRouter(HotspotVoucher $voucher, Router $router): array
    {
        $voucher->loadMissing(['hotspotProfile']);

        return DB::transaction(function () use ($voucher, $router) {
            // Check if already provisioned on this router
            $existing = HotspotService::where('hotspot_voucher_id', $voucher->id)
                ->where('router_id', $router->id)
                ->first();

            if ($existing !== null) {
                if ($existing->status === 'active') {
                    return [
                        'success' => true,
                        'mikrotik_id' => $existing->mikrotik_user_id,
                        'message' => 'Already active on this router.',
                    ];
                }

                // Reactivate failed record
                $existing->delete();
            }

            // Create HotspotService record first (provisioning state)
            $hotspotService = HotspotService::create([
                'hotspot_voucher_id' => $voucher->id,
                'router_id' => $router->id,
                'hotspot_username' => $voucher->username,
                'status' => 'provisioning',
            ]);

            // Create user on MikroTik
            $profile = $voucher->hotspotProfile;
            $plaintextPassword = $this->resolvePlaintextPassword($voucher);

            $result = $this->mikrotikService->createUser(
                router: $router,
                username: $voucher->username,
                password: $plaintextPassword,
                profile: $profile?->profile_name,
                comment: "Voucher: {$voucher->voucher_code}",
                sharedUsers: $profile?->usesMacLock() ? 1 : 3,
            );

            if ($result['success']) {
                $hotspotService->markActive($result['mikrotik_id']);

                // Sync to FreeRADIUS (shared auth — one entry for all routers)
                try {
                    $this->radiusProvider->syncVoucher($voucher->fresh()->load('hotspotProfile'));
                } catch (\Throwable $e) {
                    Log::warning('FreeRADIUS sync failed during activation', [
                        'voucher_id' => $voucher->id,
                        'username' => $voucher->username,
                        'error' => $e->getMessage(),
                    ]);
                }

                Log::info('Hotspot voucher activated on router.', [
                    'voucher_id' => $voucher->id,
                    'router_id' => $router->id,
                    'username' => $voucher->username,
                ]);

                return [
                    'success' => true,
                    'mikrotik_id' => $result['mikrotik_id'],
                    'message' => 'Activated on router.',
                ];
            }

            $hotspotService->markFailed($result['message']);

            return [
                'success' => false,
                'mikrotik_id' => null,
                'message' => $result['message'],
            ];
        });
    }

    /**
     * Activate a voucher on ALL active routers.
     *
     * @return array{total: int, succeeded: int, failed: int, results: array<string, array>}
     */
    public function activateOnAllRouters(HotspotVoucher $voucher): array
    {
        $routers = Router::query()
            ->where('is_active', true)
            ->whereNotNull('host')
            ->get();

        $succeeded = 0;
        $failed = 0;
        $results = [];

        foreach ($routers as $router) {
            $result = $this->activateOnRouter($voucher, $router);
            $results[$router->id] = $result;

            if ($result['success']) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        // Always sync to FreeRADIUS regardless of per-router results
        if ($succeeded > 0) {
            try {
                $this->radiusProvider->syncVoucher($voucher->fresh()->load('hotspotProfile'));
            } catch (\Throwable $e) {
                Log::warning('FreeRADIUS sync failed after activateOnAllRouters', [
                    'voucher_id' => $voucher->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'total' => $routers->count(),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Deactivate a voucher on a specific router.
     */
    public function deactivateOnRouter(HotspotVoucher $voucher, Router $router): array
    {
        return DB::transaction(function () use ($voucher, $router) {
            $hotspotService = HotspotService::where('hotspot_voucher_id', $voucher->id)
                ->where('router_id', $router->id)
                ->first();

            if ($hotspotService === null) {
                return [
                    'success' => true,
                    'message' => 'No hotspot service found for this router.',
                ];
            }

            // Remove from MikroTik
            $result = $this->mikrotikService->removeUser($router, $voucher->username);

            // Soft-delete the HotspotService record
            $hotspotService->update(['status' => 'removed']);

            // Check if voucher is still active on any other router
            $stillActiveElsewhere = HotspotService::where('hotspot_voucher_id', $voucher->id)
                ->where('router_id', '!=', $router->id)
                ->where('status', 'active')
                ->exists();

            // If not active anywhere else, disable in FreeRADIUS
            if (! $stillActiveElsewhere) {
                try {
                    $this->radiusProvider->disableVoucher($voucher->username);
                } catch (\Throwable $e) {
                    Log::warning('FreeRADIUS disable failed', [
                        'voucher_id' => $voucher->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Hotspot voucher deactivated from router.', [
                'voucher_id' => $voucher->id,
                'router_id' => $router->id,
                'username' => $voucher->username,
            ]);

            return [
                'success' => $result['success'],
                'message' => $result['message'],
            ];
        });
    }

    /**
     * Deactivate a voucher from ALL routers.
     *
     * @return array{total: int, succeeded: int, failed: int, results: array<string, array>}
     */
    public function deactivateFromAllRouters(HotspotVoucher $voucher): array
    {
        $hotspotServices = HotspotService::where('hotspot_voucher_id', $voucher->id)
            ->where('status', 'active')
            ->with('router')
            ->get();

        $succeeded = 0;
        $failed = 0;
        $results = [];

        foreach ($hotspotServices as $service) {
            $router = $service->router;
            $result = $this->deactivateOnRouter($voucher, $router);
            $results[$router->id] = $result;

            if ($result['success']) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        // Remove from FreeRADIUS completely
        try {
            $this->radiusProvider->removeVoucher($voucher->username);
        } catch (\Throwable $e) {
            Log::warning('FreeRADIUS removal failed', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'total' => $hotspotServices->count(),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Sync a voucher's FreeRADIUS entry (call after profile change, etc.).
     */
    public function syncToFreeRadius(HotspotVoucher $voucher): void
    {
        $voucher->loadMissing(['hotspotProfile']);

        try {
            $this->radiusProvider->syncVoucher($voucher);
        } catch (\Throwable $e) {
            Log::error('FreeRADIUS sync failed', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get HotspotService records for a voucher across all routers.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, HotspotService>
     */
    public function getServicesForVoucher(HotspotVoucher $voucher): \Illuminate\Database\Eloquent\Collection
    {
        return HotspotService::where('hotspot_voucher_id', $voucher->id)
            ->with('router:id,router_name,router_code,host,is_active')
            ->orderBy('router_id')
            ->get();
    }

    /**
     * Get active HotspotService records for a router.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, HotspotService>
     */
    public function getActiveServicesForRouter(Router $router): \Illuminate\Database\Eloquent\Collection
    {
        return HotspotService::where('router_id', $router->id)
            ->where('status', 'active')
            ->with('voucher.hotspotProfile')
            ->get();
    }

    /**
     * Resolve plaintext password for a voucher.
     *
     * HotspotVoucher's password is encrypted in DB. password_plain stores the
     * plaintext at creation time for FreeRADIUS and MikroTik provisioning.
     */
    private function resolvePlaintextPassword(HotspotVoucher $voucher): string
    {
        $voucher->refresh();

        $plain = $voucher->getRawOriginal('password_plain');

        if ($plain !== null && $plain !== '') {
            return $plain;
        }

        $raw = $voucher->getRawOriginal('password');

        if ($raw !== null) {
            return $raw;
        }

        $passwordAttr = $voucher->getAttribute('password');

        if (str_starts_with((string) $passwordAttr, 'eyJp')) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString($passwordAttr);
            } catch (\Throwable) {
                return $passwordAttr;
            }
        }

        return (string) $passwordAttr;
    }
}
