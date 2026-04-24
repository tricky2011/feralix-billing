<?php

namespace App\Services\Provisioning;

use App\Enums\ServiceIsolationStatus;
use App\Enums\ServiceRouterOperationJobStatus;
use App\Enums\ServiceRouterOperationLogAction;
use App\Enums\ServiceRouterOperationType;
use App\Exceptions\Mikrotik\MikrotikApiException;
use App\Models\ServiceRouterOperationJob;
use App\Models\ServiceRouterOperationLog;
use App\Models\ServiceRouterOperationStatus;
use App\Services\Mikrotik\MikrotikAddressListService;
use Illuminate\Log\LogManager;
use Illuminate\Validation\ValidationException;

class ServiceIsolationRouterExecutionService
{
    public function __construct(
        private readonly MikrotikAddressListService $addressListService,
        private readonly ServiceIsolationService $serviceIsolationService,
        private readonly LogManager $logManager,
    ) {}

    public function execute(int $operationJobId): void
    {
        $operationJob = ServiceRouterOperationJob::query()
            ->with([
                'service:id,customer_id,router_id,vid_id,service_code,access_mode,static_ip_address,static_mac_address,static_queue_name,address_list_name,dhcp_pool_start',
                'service.vid:id,pool_start_ip',
                'service.routerOperationStatus',
                'serviceIsolation:id,service_id,router_id,status,address_list_name,target_identifier,target_payload',
                'router:id,router_code,router_name,mgmt_ip,api_port,api_username,api_password,is_active',
            ])
            ->findOrFail($operationJobId);

        if ($operationJob->job_status === ServiceRouterOperationJobStatus::Completed) {
            return;
        }

        $operationJob->update([
            'job_status' => ServiceRouterOperationJobStatus::Running->value,
            'attempts' => $operationJob->attempts + 1,
            'started_at' => $operationJob->started_at ?? now(),
            'summary' => null,
        ]);

        if ($operationJob->service === null || $operationJob->router === null) {
            $this->markJobFailed($operationJob, 'Service router operation cannot run because related service or router is missing.');

            return;
        }

        if (
            $operationJob->address_list_name === null || trim($operationJob->address_list_name) === '' ||
            $operationJob->target_address === null || trim($operationJob->target_address) === ''
        ) {
            $this->markJobFailed($operationJob, 'Service router operation cannot run because target address-list data is incomplete.');

            return;
        }

        if (
            $operationJob->operation_type === ServiceRouterOperationType::Isolate &&
            $operationJob->serviceIsolation?->status === ServiceIsolationStatus::Released
        ) {
            $this->markJobCompleted($operationJob, ServiceRouterOperationLogAction::Skipped, 'Isolation was already released before the router job started.');

            return;
        }

        try {
            $result = match ($operationJob->operation_type) {
                ServiceRouterOperationType::Isolate => $this->addressListService->ensureAddressListed(
                    $operationJob->router,
                    $operationJob->address_list_name,
                    $operationJob->target_address,
                    $this->buildComment($operationJob),
                ),
                ServiceRouterOperationType::Release => $this->addressListService->ensureAddressRemoved(
                    $operationJob->router,
                    $operationJob->address_list_name,
                    $operationJob->target_address,
                ),
            };
        } catch (MikrotikApiException $exception) {
            $this->markJobFailed(
                $operationJob,
                $exception->getMessage(),
                ServiceRouterOperationLogAction::Failed,
                [
                    'exception' => get_class($exception),
                ],
            );

            throw $exception;
        }

        $action = ServiceRouterOperationLogAction::from($result['action']);

        $this->storeLog(
            $operationJob,
            $action,
            $this->buildResultMessage($operationJob, $action),
            $result['router_item_id'] ?? null,
            [
                'matched_entries' => $result['matched'] ?? 0,
            ],
        );

        if ($operationJob->operation_type === ServiceRouterOperationType::Isolate) {
            $this->applyIsolationStateIfNeeded($operationJob);
            $this->syncRouterOperationStatus($operationJob, true);
        } else {
            $this->syncRouterOperationStatus($operationJob, false);
        }

        $this->markJobCompleted(
            $operationJob,
            $action,
            $this->buildResultMessage($operationJob, $action),
        );
    }

    private function applyIsolationStateIfNeeded(ServiceRouterOperationJob $operationJob): void
    {
        $serviceIsolation = $operationJob->serviceIsolation?->refresh();

        if ($serviceIsolation === null || $serviceIsolation->status !== ServiceIsolationStatus::Pending) {
            return;
        }

        try {
            $this->serviceIsolationService->markApplied($serviceIsolation, [
                'isolated_at' => now(),
                'notes' => 'Applied automatically via Mikrotik address-list queue.',
            ]);
        } catch (ValidationException $exception) {
            $this->logger()->warning('Automatic Mikrotik isolation state sync skipped because the isolation state changed concurrently.', [
                'operation_job_id' => $operationJob->id,
                'service_isolation_id' => $serviceIsolation->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function syncRouterOperationStatus(ServiceRouterOperationJob $operationJob, bool $addressListFound): void
    {
        $service = $operationJob->service?->refresh();

        if ($service === null) {
            return;
        }

        $operationStatus = $service->routerOperationStatus()->first()
            ?? new ServiceRouterOperationStatus([
                'service_id' => $service->id,
            ]);

        $operationStatus->fill([
            'router_id' => $service->router_id,
            'access_mode' => $service->resolvedAccessMode()->value,
            'isolation_target_type' => $service->resolvedIsolationTargetType()->value,
            'pppoe_username' => $service->operationalPppoeUsername(),
            'static_ip_address' => $service->operationalStaticIpAddress(),
            'static_mac_address' => $service->static_mac_address,
            'queue_name' => $service->static_queue_name,
            'address_list_name' => $operationJob->address_list_name,
            'address_list_target' => $addressListFound ? $operationJob->target_address : null,
            'address_list_found' => $addressListFound,
            'isolation_detected_via' => $addressListFound ? 'address_list' : null,
            'last_synced_at' => now(),
            'isolation_verified_at' => $addressListFound ? now() : null,
        ]);

        if (! $operationStatus->exists || $operationStatus->isDirty()) {
            $operationStatus->save();
        }
    }

    private function markJobCompleted(
        ServiceRouterOperationJob $operationJob,
        ServiceRouterOperationLogAction $action,
        string $message,
    ): void {
        $operationJob->update([
            'job_status' => ServiceRouterOperationJobStatus::Completed->value,
            'finished_at' => now(),
            'summary' => $message,
        ]);

        $this->logger()->info('Service router operation job completed.', [
            'operation_job_id' => $operationJob->id,
            'service_id' => $operationJob->service_id,
            'service_isolation_id' => $operationJob->service_isolation_id,
            'router_id' => $operationJob->router_id,
            'operation_type' => $operationJob->operation_type?->value,
            'action_type' => $action->value,
            'address_list_name' => $operationJob->address_list_name,
            'target_address' => $operationJob->target_address,
            'message' => $message,
        ]);
    }

    private function markJobFailed(
        ServiceRouterOperationJob $operationJob,
        string $message,
        ServiceRouterOperationLogAction $action = ServiceRouterOperationLogAction::Failed,
        ?array $context = null,
    ): void {
        $operationJob->update([
            'job_status' => ServiceRouterOperationJobStatus::Failed->value,
            'finished_at' => now(),
            'summary' => $message,
        ]);

        $this->storeLog($operationJob, $action, $message, null, $context);

        $this->logger()->error('Service router operation job failed.', [
            'operation_job_id' => $operationJob->id,
            'service_id' => $operationJob->service_id,
            'service_isolation_id' => $operationJob->service_isolation_id,
            'router_id' => $operationJob->router_id,
            'operation_type' => $operationJob->operation_type?->value,
            'address_list_name' => $operationJob->address_list_name,
            'target_address' => $operationJob->target_address,
            'message' => $message,
            ...($context ?? []),
        ]);
    }

    private function storeLog(
        ServiceRouterOperationJob $operationJob,
        ServiceRouterOperationLogAction $action,
        string $message,
        ?string $routerItemId = null,
        ?array $context = null,
    ): void {
        ServiceRouterOperationLog::query()->create([
            'operation_job_id' => $operationJob->id,
            'service_id' => $operationJob->service_id,
            'service_isolation_id' => $operationJob->service_isolation_id,
            'router_id' => $operationJob->router_id,
            'action_type' => $action->value,
            'address_list_name' => $operationJob->address_list_name,
            'target_address' => $operationJob->target_address,
            'router_item_id' => $routerItemId,
            'context' => $context,
            'message' => $message,
            'created_at' => now(),
        ]);
    }

    private function buildComment(ServiceRouterOperationJob $operationJob): string
    {
        $serviceCode = $operationJob->service?->service_code ?? 'unknown';

        return sprintf(
            'feralix-billing %s service:%s isolation:%d',
            $operationJob->operation_type?->value ?? 'isolate',
            $serviceCode,
            $operationJob->service_isolation_id,
        );
    }

    private function buildResultMessage(
        ServiceRouterOperationJob $operationJob,
        ServiceRouterOperationLogAction $action,
    ): string {
        return sprintf(
            'Router operation %s finished with action %s on %s/%s.',
            $operationJob->operation_type?->value ?? 'unknown',
            $action->value,
            $operationJob->address_list_name ?? 'unknown-list',
            $operationJob->target_address ?? 'unknown-target',
        );
    }

    private function logger()
    {
        $channel = (string) (config('mikrotik.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }
}
