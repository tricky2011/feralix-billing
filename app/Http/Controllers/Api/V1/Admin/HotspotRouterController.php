<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HotspotRouter\ActivateHotspotOnRouterRequest;
use App\Http\Requests\HotspotRouter\ActivateHotspotOnAllRoutersRequest;
use App\Http\Requests\HotspotRouter\SyncHotspotRequest;
use App\Jobs\ProvisionHotspotJob;
use App\Models\HotspotVoucher;
use App\Models\Router;
use App\Services\Hotspot\CentralizedHotspotService;
use Illuminate\Http\JsonResponse;

/**
 * Admin API for managing hotspot provisioning across routers.
 *
 * Endpoints:
 * - POST   /api/v1/admin/hotspot-router/activate       Activate voucher on a router
 * - POST   /api/v1/admin/hotspot-router/activate-all Activate voucher on ALL routers
 * - DELETE /api/v1/admin/hotspot-router/deactivate    Deactivate voucher from a router
 * - DELETE /api/v1/admin/hotspot-router/deactivate-all Deactivate from all routers
 * - POST   /api/v1/admin/hotspot-router/sync-radius   Re-sync FreeRADIUS entry
 * - GET    /api/v1/admin/hotspot-router/services/{voucher}  Get HotspotService records
 * - GET    /api/v1/admin/hotspot-router/routers/{router}/services  Get services on router
 */
class HotspotRouterController extends Controller
{
    public function __construct(
        private readonly CentralizedHotspotService $centralizedService,
    ) {}

    /**
     * Activate a voucher on a specific router.
     *
     * POST /api/v1/admin/hotspot-router/activate
     */
    public function activate(ActivateHotspotOnRouterRequest $request): JsonResponse
    {
        $voucher = HotspotVoucher::query()->findOrFail((int) $request->validated('voucher_id'));
        $router = Router::query()->findOrFail((int) $request->validated('router_id'));

        // Dispatch async job
        ProvisionHotspotJob::dispatch(
            voucherId: $voucher->id,
            action: 'activate_on_router',
            routerId: $router->id,
        );

        return $this->successResponse(
            "Activation queued for {$voucher->username} on router {$router->name}.",
            ['voucher_id' => $voucher->id, 'router_id' => $router->id]
        );
    }

    /**
     * Activate a voucher on ALL active routers.
     *
     * POST /api/v1/admin/hotspot-router/activate-all
     */
    public function activateAll(ActivateHotspotOnAllRoutersRequest $request): JsonResponse
    {
        $voucher = HotspotVoucher::query()->findOrFail((int) $request->validated('voucher_id'));

        ProvisionHotspotJob::dispatch(
            voucherId: $voucher->id,
            action: 'activate_on_all',
        );

        return $this->successResponse(
            "Activation on all routers queued for {$voucher->username}.",
            ['voucher_id' => $voucher->id]
        );
    }

    /**
     * Deactivate a voucher from a specific router.
     *
     * DELETE /api/v1/admin/hotspot-router/deactivate
     */
    public function deactivate(ActivateHotspotOnRouterRequest $request): JsonResponse
    {
        $voucher = HotspotVoucher::query()->findOrFail((int) $request->validated('voucher_id'));
        $router = Router::query()->findOrFail((int) $request->validated('router_id'));

        ProvisionHotspotJob::dispatch(
            voucherId: $voucher->id,
            action: 'deactivate_on_router',
            routerId: $router->id,
        );

        return $this->successResponse(
            "Deactivation queued for {$voucher->username} from router {$router->name}.",
            ['voucher_id' => $voucher->id, 'router_id' => $router->id]
        );
    }

    /**
     * Deactivate a voucher from ALL routers.
     *
     * DELETE /api/v1/admin/hotspot-router/deactivate-all
     */
    public function deactivateAll(ActivateHotspotOnAllRoutersRequest $request): JsonResponse
    {
        $voucher = HotspotVoucher::query()->findOrFail((int) $request->validated('voucher_id'));

        ProvisionHotspotJob::dispatch(
            voucherId: $voucher->id,
            action: 'deactivate_from_all',
        );

        return $this->successResponse(
            "Deactivation from all routers queued for {$voucher->username}.",
            ['voucher_id' => $voucher->id]
        );
    }

    /**
     * Re-sync a voucher's FreeRADIUS entry.
     *
     * POST /api/v1/admin/hotspot-router/sync-radius
     */
    public function syncRadius(SyncHotspotRequest $request): JsonResponse
    {
        $voucher = HotspotVoucher::query()->findOrFail((int) $request->validated('voucher_id'));

        ProvisionHotspotJob::dispatch(
            voucherId: $voucher->id,
            action: 'sync_freeradius',
        );

        return $this->successResponse(
            "FreeRADIUS sync queued for {$voucher->username}.",
            ['voucher_id' => $voucher->id]
        );
    }

    /**
     * Get all HotspotService records for a voucher (across all routers).
     *
     * GET /api/v1/admin/hotspot-router/services/{voucher}
     */
    public function getVoucherServices(HotspotVoucher $hotspotVoucher): JsonResponse
    {
        $services = $this->centralizedService->getServicesForVoucher($hotspotVoucher);

        return $this->successResponse('Voucher services retrieved.', [
            'services' => $services->map(fn ($s) => [
                'id' => $s->id,
                'router_id' => $s->router_id,
                'router_name' => $s->router?->router_name,
                'hotspot_username' => $s->hotspot_username,
                'mikrotik_user_id' => $s->mikrotik_user_id,
                'status' => $s->status,
                'sync_error' => $s->sync_error,
                'created_at' => $s->created_at->toISOString(),
            ]),
            'total' => $services->count(),
            'voucher' => [
                'id' => $hotspotVoucher->id,
                'username' => $hotspotVoucher->username,
                'voucher_code' => $hotspotVoucher->voucher_code,
            ],
        ]);
    }

    /**
     * Get all active hotspot service records on a specific router.
     *
     * GET /api/v1/admin/hotspot-router/routers/{router}/services
     */
    public function getRouterServices(Router $router): JsonResponse
    {
        $services = $this->centralizedService->getActiveServicesForRouter($router);

        return $this->successResponse('Router services retrieved.', [
            'services' => $services->map(fn ($s) => [
                'id' => $s->id,
                'voucher_id' => $s->hotspot_voucher_id,
                'username' => $s->hotspot_username,
                'voucher_code' => $s->voucher?->voucher_code,
                'profile' => $s->voucher?->hotspotProfile?->profile_name,
                'status' => $s->status,
            ]),
            'total' => $services->count(),
            'router' => [
                'id' => $router->id,
                'name' => $router->router_name ?? $router->name,
                'host' => $router->host,
            ],
        ]);
    }
}
