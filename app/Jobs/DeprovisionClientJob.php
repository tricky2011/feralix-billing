<?php

namespace App\Jobs;

use App\Enums\ServiceOverallStatus;
use App\Enums\VidStatus;
use App\Models\Service;
use App\Services\Mikrotik\MikrotikPppoeSecretService;
use App\Services\Mikrotik\MikrotikVlanService;
use App\Services\Provisioning\PppoeCredentialService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeprovisionClientJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithAutomationLogging;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [30, 120, 300];

    public function __construct(
        private readonly int $serviceId,
    ) {
        $this->afterCommit();
        $this->onQueue(config('automation.queues.provisioning', 'provisioning'));
    }

    public function handle(
        MikrotikPppoeSecretService $pppoeSecretService,
        MikrotikVlanService $vlanService,
        PppoeCredentialService $credentialService,
    ): void {
        $this->logAutomationStarted('deprovisioning.client', [
            'service_id' => $this->serviceId,
        ]);

        $service = Service::query()
            ->with(['router', 'vid', 'customer'])
            ->find($this->serviceId);

        if ($service === null) {
            $this->logAutomationFailed(
                'deprovisioning.client',
                new \RuntimeException('Service not found.'),
                ['service_id' => $this->serviceId]
            );

            return;
        }

        // Check if already terminated
        if ($service->overall_status === ServiceOverallStatus::Terminated->value) {
            $this->logAutomationFinished('deprovisioning.client', [
                'service_id' => $this->serviceId,
                'skipped' => true,
                'reason' => 'Already terminated',
            ]);

            return;
        }

        $results = [];

        // Execute deprovisioning in a transaction
        DB::transaction(function () use ($service, $pppoeSecretService, $vlanService, &$results) {
            $router = $service->router;
            $vid = $service->vid;

            // 1. Remove PPPoE secret if exists
            if ($service->pppoe_username && $router && $router->is_active) {
                $results['pppoe_secret'] = $pppoeSecretService->removeSecret(
                    $router,
                    $service->pppoe_username
                );
            }

            // 2. Deprovision VID if assigned
            if ($vid && $router && $router->is_active) {
                $results['vid_deprovision'] = $vlanService->deprovisionVlan($router, $vid);

                // Release VID back to available pool
                $vid->update([
                    'service_id' => null,
                    'status' => VidStatus::Available->value,
                ]);

                $results['vid_release'] = [
                    'success' => true,
                    'vid_id' => $vid->id,
                    'message' => 'VID released to available pool.',
                ];
            }

            // 3. Update service status to terminated
            $service->update([
                'overall_status' => ServiceOverallStatus::Terminated->value,
                'notes' => trim(($service->notes ?? '') . "\n\n[" . now()->toISOString() . '] Terminated via DeprovisionClientJob.'),
            ]);

            // 4. Update customer status if no other active services
            $customer = $service->customer;
            if ($customer) {
                $hasActiveServices = Service::query()
                    ->where('customer_id', $customer->id)
                    ->where('overall_status', '!=', ServiceOverallStatus::Terminated->value)
                    ->where('id', '!=', $service->id)
                    ->exists();

                if (! $hasActiveServices) {
                    $customer->update(['status' => 'terminated']);
                    $results['customer_status'] = [
                        'success' => true,
                        'message' => 'Customer status updated to terminated.',
                    ];
                }
            }
        });

        $hasFailure = collect($results)->contains(fn ($result) => is_array($result) && ! ($result['success'] ?? false));

        if ($hasFailure) {
            $this->logAutomationFailed(
                'deprovisioning.client',
                new \RuntimeException('Some operations failed.'),
                [
                    'service_id' => $this->serviceId,
                    'results' => $results,
                ]
            );

            return;
        }

        $this->logAutomationFinished('deprovisioning.client', [
            'service_id' => $this->serviceId,
            'results' => $results,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('deprovisioning.client', $exception, [
            'service_id' => $this->serviceId,
        ]);
    }
}
