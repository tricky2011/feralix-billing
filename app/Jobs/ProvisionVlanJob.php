<?php

namespace App\Jobs;

use App\Models\Vid;
use App\Services\Mikrotik\MikrotikVlanService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProvisionVlanJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithAutomationLogging;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [30, 120, 300];

    /**
     * Action types:
     * - create: Provision VLAN (VLAN interface + IP pool + DHCP + Queue)
     * - remove: Deprovision VLAN (remove all components)
     * - update_queue: Update queue rate limit only
     */
    public function __construct(
        private readonly int $vidId,
        private readonly string $action = 'create',
    ) {
        $this->afterCommit();
        $this->onQueue(config('automation.queues.network', 'network'));
    }

    public function handle(MikrotikVlanService $vlanService): void
    {
        $this->logAutomationStarted('provisioning.vlan', [
            'vid_id' => $this->vidId,
            'action' => $this->action,
        ]);

        $vid = Vid::query()
            ->with('router')
            ->find($this->vidId);

        if ($vid === null) {
            $this->logAutomationFailed(
                'provisioning.vlan',
                new \RuntimeException('VID not found.'),
                ['vid_id' => $this->vidId]
            );

            return;
        }

        $router = $vid->router;

        if ($router === null || ! $router->is_active) {
            $this->logAutomationFailed(
                'provisioning.vlan',
                new \RuntimeException('Router not available.'),
                [
                    'vid_id' => $this->vidId,
                    'router_id' => $vid->router_id,
                ]
            );

            return;
        }

        $result = match ($this->action) {
            'create' => $vlanService->provisionVlan(
                $router,
                $vid,
                (int) ($vid->rate_limit_mbps ?: 10)
            ),

            'remove' => $vlanService->deprovisionVlan($router, $vid),

            'update_queue' => $vlanService->provisionVlan(
                $router,
                $vid,
                (int) ($vid->rate_limit_mbps ?: 10)
            ),

            default => [
                'success' => false,
                'message' => "Unknown action: {$this->action}",
            ],
        };

        if (! ($result['success'] ?? false)) {
            $this->logAutomationFailed(
                'provisioning.vlan',
                new \RuntimeException($result['message'] ?? 'Unknown error'),
                [
                    'vid_id' => $this->vidId,
                    'action' => $this->action,
                    'result' => $result,
                ]
            );

            return;
        }

        $this->logAutomationFinished('provisioning.vlan', [
            'vid_id' => $this->vidId,
            'action' => $this->action,
            'result' => $result,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('provisioning.vlan', $exception, [
            'vid_id' => $this->vidId,
            'action' => $this->action,
        ]);
    }
}
