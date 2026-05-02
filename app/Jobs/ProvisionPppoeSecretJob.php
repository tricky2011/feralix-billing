<?php

namespace App\Jobs;

use App\Models\Service;
use App\Services\Mikrotik\MikrotikPppoeSecretService;
use App\Services\Provisioning\PppoeCredentialService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProvisionPppoeSecretJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithAutomationLogging;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 120, 300];

    /**
     * Action types:
     * - create: Create new PPPoE secret
     * - remove: Remove PPPoE secret
     * - update_profile: Change PPPoE profile (for isolation)
     * - update_password: Change PPPoE password
     * - update_comment: Update comment only
     */
    public function __construct(
        private readonly int $serviceId,
        private readonly string $action = 'create',
    ) {
        $this->afterCommit();
        $this->onQueue(config('automation.queues.provisioning', 'provisioning'));
    }

    public function handle(
        MikrotikPppoeSecretService $pppoeSecretService,
        PppoeCredentialService $credentialService,
    ): void {
        $this->logAutomationStarted('provisioning.pppoe_secret', [
            'service_id' => $this->serviceId,
            'action' => $this->action,
        ]);

        $service = Service::query()
            ->with(['router', 'customer'])
            ->find($this->serviceId);

        if ($service === null) {
            $this->logAutomationFailed(
                'provisioning.pppoe_secret',
                new \RuntimeException('Service not found.'),
                ['service_id' => $this->serviceId]
            );

            return;
        }

        $router = $service->router;

        if ($router === null || ! $router->is_active) {
            $this->logAutomationFailed(
                'provisioning.pppoe_secret',
                new \RuntimeException('Router not available.'),
                [
                    'service_id' => $this->serviceId,
                    'router_id' => $service->router_id,
                ]
            );

            return;
        }

        $username = $service->pppoe_username ?? $credentialService->generateUsername($service);
        $password = $service->pppoe_password ?? $credentialService->generatePassword(now());
        $profile = config('mikrotik.pppoe.default_profile', 'default');
        $comment = $this->buildComment($service);

        $result = match ($this->action) {
            'create' => $pppoeSecretService->createSecret(
                $router,
                $username,
                $password,
                $profile,
                $comment,
            ),

            'remove' => $pppoeSecretService->removeSecret($router, $username),

            'update_profile' => $pppoeSecretService->setSecretProfile(
                $router,
                $username,
                $service->pppoe_isolation_profile ?? $profile,
            ),

            'update_password' => $pppoeSecretService->setSecretPassword(
                $router,
                $username,
                $password,
            ),

            'update_comment' => $pppoeSecretService->setSecretComment(
                $router,
                $username,
                $comment,
            ),

            default => [
                'success' => false,
                'message' => "Unknown action: {$this->action}",
            ],
        };

        if (! ($result['success'] ?? false)) {
            $this->logAutomationFailed(
                'provisioning.pppoe_secret',
                new \RuntimeException($result['message'] ?? 'Unknown error'),
                [
                    'service_id' => $this->serviceId,
                    'action' => $this->action,
                    'result' => $result,
                ]
            );

            return;
        }

        $this->logAutomationFinished('provisioning.pppoe_secret', [
            'service_id' => $this->serviceId,
            'action' => $this->action,
            'username' => $username,
            'result' => $result,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('provisioning.pppoe_secret', $exception, [
            'service_id' => $this->serviceId,
            'action' => $this->action,
        ]);
    }

    private function buildComment(Service $service): string
    {
        $parts = [
            'feralix-billing',
            'service:'.$service->service_code,
            'customer:'.$service->customer?->full_name ?? 'N/A',
            'created:'.now()->toDateString(),
        ];

        return implode(' | ', $parts);
    }
}
