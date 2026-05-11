<?php

namespace App\Jobs;

use App\Models\HotspotVoucher;
use App\Models\Router;
use App\Services\Hotspot\CentralizedHotspotService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Provisions (creates/removes/updates) hotspot users on MikroTik routers.
 *
 * Action types:
 * - activate_on_router  : Create hotspot user on a specific router
 * - deactivate_on_router: Remove hotspot user from a specific router
 * - activate_on_all     : Create hotspot user on ALL active routers
 * - deactivate_from_all: Remove hotspot user from ALL routers
 * - sync_freeradius     : Re-sync FreeRADIUS entry for a voucher
 */
class ProvisionHotspotJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithAutomationLogging;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 120, 300];

    public function __construct(
        private readonly int $voucherId,
        private readonly string $action,
        private readonly ?int $routerId = null,
    ) {
        $this->afterCommit();
        $this->onQueue(config('automation.queues.provisioning', 'provisioning'));
    }

    public function handle(CentralizedHotspotService $centralizedService): void
    {
        $this->logAutomationStarted('provisioning.hotspot', [
            'voucher_id' => $this->voucherId,
            'action' => $this->action,
            'router_id' => $this->routerId,
        ]);

        $voucher = HotspotVoucher::query()
            ->with('hotspotProfile')
            ->find($this->voucherId);

        if ($voucher === null) {
            $this->logAutomationFailed(
                'provisioning.hotspot',
                new \RuntimeException('HotspotVoucher not found.'),
                ['voucher_id' => $this->voucherId],
            );

            return;
        }

        $result = match ($this->action) {
            'activate_on_router' => $this->handleActivateOnRouter($voucher, $centralizedService),
            'deactivate_on_router' => $this->handleDeactivateOnRouter($voucher, $centralizedService),
            'activate_on_all' => $this->handleActivateOnAll($voucher, $centralizedService),
            'deactivate_from_all' => $this->handleDeactivateFromAll($voucher, $centralizedService),
            'sync_freeradius' => $this->handleSyncFreeRadius($voucher, $centralizedService),
            default => [
                'success' => false,
                'message' => "Unknown action: {$this->action}",
            ],
        };

        if (! ($result['success'] ?? false)) {
            $this->logAutomationFailed(
                'provisioning.hotspot',
                new \RuntimeException($result['message'] ?? 'Unknown error'),
                [
                    'voucher_id' => $this->voucherId,
                    'action' => $this->action,
                    'router_id' => $this->routerId,
                    'result' => $result,
                ],
            );

            return;
        }

        $this->logAutomationFinished('provisioning.hotspot', [
            'voucher_id' => $this->voucherId,
            'action' => $this->action,
            'router_id' => $this->routerId,
            'result' => $result,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('provisioning.hotspot', $exception, [
            'voucher_id' => $this->voucherId,
            'action' => $this->action,
            'router_id' => $this->routerId,
        ]);
    }

    private function handleActivateOnRouter(HotspotVoucher $voucher, CentralizedHotspotService $centralizedService): array
    {
        $router = Router::query()->find($this->routerId);

        if ($router === null) {
            return [
                'success' => false,
                'message' => 'Router not found.',
            ];
        }

        return $centralizedService->activateOnRouter($voucher, $router);
    }

    private function handleDeactivateOnRouter(HotspotVoucher $voucher, CentralizedHotspotService $centralizedService): array
    {
        $router = Router::query()->find($this->routerId);

        if ($router === null) {
            return [
                'success' => false,
                'message' => 'Router not found.',
            ];
        }

        return $centralizedService->deactivateOnRouter($voucher, $router);
    }

    private function handleActivateOnAll(HotspotVoucher $voucher, CentralizedHotspotService $centralizedService): array
    {
        return $centralizedService->activateOnAllRouters($voucher);
    }

    private function handleDeactivateFromAll(HotspotVoucher $voucher, CentralizedHotspotService $centralizedService): array
    {
        return $centralizedService->deactivateFromAllRouters($voucher);
    }

    private function handleSyncFreeRadius(HotspotVoucher $voucher, CentralizedHotspotService $centralizedService): array
    {
        try {
            $centralizedService->syncToFreeRadius($voucher);

            return [
                'success' => true,
                'message' => 'FreeRADIUS sync completed.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
