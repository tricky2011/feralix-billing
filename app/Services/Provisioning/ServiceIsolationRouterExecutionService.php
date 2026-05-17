<?php

namespace App\Services\Provisioning;

use App\Enums\ServiceIsolationStatus;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\ServiceRouterOperationJobStatus;
use App\Enums\ServiceRouterOperationLogAction;
use App\Enums\ServiceRouterOperationType;
use App\Exceptions\Mikrotik\MikrotikApiException;
use App\Models\Service;
use App\Models\ServiceIsolation;
use App\Models\ServiceMonitorPppoeStatus;
use App\Models\ServiceRouterOperationJob;
use App\Models\ServiceRouterOperationLog;
use App\Models\ServiceRouterOperationStatus;
use App\Services\Helpdesk\TelegramAlertService;
use App\Services\Mikrotik\MikrotikAddressListService;
use Illuminate\Log\LogManager;
use Illuminate\Validation\ValidationException;

class ServiceIsolationRouterExecutionService
{
    public function __construct(
        private readonly MikrotikAddressListService $addressListService,
        private readonly ServiceIsolationService $serviceIsolationService,
        private readonly ServiceIsolationRouterJobDispatcher $routerJobDispatcher,
        private readonly TelegramAlertService $telegramAlertService,
        private readonly LogManager $logManager,
    ) {}

    public function execute(int $operationJobId): void
    {
        $operationJob = ServiceRouterOperationJob::query()
            ->with([
                'service:id,customer_id,router_id,vid_id,service_code,access_mode,static_ip_address,static_mac_address,static_queue_name,address_list_name,dhcp_pool_start',
                'service.vid:id,vid,pool_start_ip',
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
                    $this->buildLegacyCommentPatterns($operationJob),
                ),
            };
        } catch (MikrotikApiException $exception) {
            $isLastAttempt = $operationJob->attempts >= $this->getJobTries();

            if ($isLastAttempt) {
                $this->markServiceIsolationFailed($operationJob, $exception->getMessage());
            }

            $this->markJobFailed(
                $operationJob,
                $exception->getMessage(),
                ServiceRouterOperationLogAction::Failed,
                [
                    'exception' => get_class($exception),
                    'is_last_attempt' => $isLastAttempt,
                    'attempts' => $operationJob->attempts,
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
            $this->applyReleaseStateIfNeeded($operationJob);
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
            $this->handleIsolationStateConflict($operationJob, $exception);
        }
    }

    private function handleIsolationStateConflict(
        ServiceRouterOperationJob $operationJob,
        ValidationException $exception
    ): void {
        $serviceIsolation = ServiceIsolation::query()
            ->with(['service', 'service.customer'])
            ->find($operationJob->service_isolation_id);

        if ($serviceIsolation === null) {
            $this->logger()->error('Isolation state conflict: ServiceIsolation record not found in database.', [
                'operation_job_id' => $operationJob->id,
                'service_isolation_id' => $operationJob->service_isolation_id,
                'target_address' => $operationJob->target_address,
                'address_list_name' => $operationJob->address_list_name,
            ]);

            $this->dispatchReleaseJob($operationJob);
            $this->sendConflictAlert($operationJob, null, 'record_not_found');

            return;
        }

        $currentStatus = $serviceIsolation->status;

        match (true) {
            $currentStatus === ServiceIsolationStatus::Released => $this->handleReleasedConflict($operationJob, $serviceIsolation),
            $currentStatus === ServiceIsolationStatus::Failed => $this->handleFailedConflict($operationJob, $serviceIsolation),
            $currentStatus === ServiceIsolationStatus::Applied => $this->handleAppliedConflict($operationJob, $serviceIsolation),
            $currentStatus === ServiceIsolationStatus::ReleasePending => $this->handleReleasePendingConflict($operationJob, $serviceIsolation),
            default => $this->handleUnknownConflict($operationJob, $serviceIsolation),
        };
    }

    private function handleReleasedConflict(
        ServiceRouterOperationJob $operationJob,
        ServiceIsolation $serviceIsolation
    ): void {
        $this->logger()->error('Isolation state conflict: IP is isolated in router but database shows Released. Dispatching immediate cleanup.', [
            'operation_job_id' => $operationJob->id,
            'service_isolation_id' => $serviceIsolation->id,
            'service_id' => $operationJob->service_id,
            'router_id' => $operationJob->router_id,
            'target_address' => $operationJob->target_address,
            'address_list_name' => $operationJob->address_list_name,
            'current_status' => $serviceIsolation->status->value,
        ]);

        $this->dispatchReleaseJob($operationJob);
        $this->updateMonitoringStatusesAfterConflict($operationJob, true);
        $this->sendConflictAlert($operationJob, $serviceIsolation, 'ip_isolated_but_released');
    }

    private function handleFailedConflict(
        ServiceRouterOperationJob $operationJob,
        ServiceIsolation $serviceIsolation
    ): void {
        $this->logger()->error('Isolation state conflict: IP is isolated in router but database shows Failed. Dispatching immediate cleanup.', [
            'operation_job_id' => $operationJob->id,
            'service_isolation_id' => $serviceIsolation->id,
            'service_id' => $operationJob->service_id,
            'router_id' => $operationJob->router_id,
            'target_address' => $operationJob->target_address,
            'address_list_name' => $operationJob->address_list_name,
            'current_status' => $serviceIsolation->status->value,
        ]);

        $this->dispatchReleaseJob($operationJob);
        $this->updateMonitoringStatusesAfterConflict($operationJob, true);
        $this->sendConflictAlert($operationJob, $serviceIsolation, 'ip_isolated_but_failed');
    }

    private function handleAppliedConflict(
        ServiceRouterOperationJob $operationJob,
        ServiceIsolation $serviceIsolation
    ): void {
        $this->logger()->info('Isolation state conflict resolved: DB already shows Applied. Router isolation confirmed.', [
            'operation_job_id' => $operationJob->id,
            'service_isolation_id' => $serviceIsolation->id,
            'service_id' => $operationJob->service_id,
            'current_status' => $serviceIsolation->status->value,
        ]);

        $this->updateMonitoringStatusesAfterConflict($operationJob, true);
        $this->storeLog(
            $operationJob,
            ServiceRouterOperationLogAction::Completed,
            'Concurrent isolation detected: DB state already Applied. Router operation completed.',
            null,
            ['conflict_resolution' => 'already_applied'],
        );
    }

    private function handleReleasePendingConflict(
        ServiceRouterOperationJob $operationJob,
        ServiceIsolation $serviceIsolation
    ): void {
        $this->logger()->error('Isolation state conflict: IP isolated but release already pending in DB. Dispatching immediate cleanup.', [
            'operation_job_id' => $operationJob->id,
            'service_isolation_id' => $serviceIsolation->id,
            'service_id' => $operationJob->service_id,
            'router_id' => $operationJob->router_id,
            'target_address' => $operationJob->target_address,
            'address_list_name' => $operationJob->address_list_name,
            'current_status' => $serviceIsolation->status->value,
        ]);

        $this->dispatchReleaseJob($operationJob);
        $this->updateMonitoringStatusesAfterConflict($operationJob, true);
        $this->sendConflictAlert($operationJob, $serviceIsolation, 'ip_isolated_but_release_pending');
    }

    private function handleUnknownConflict(
        ServiceRouterOperationJob $operationJob,
        ServiceIsolation $serviceIsolation
    ): void {
        $this->logger()->error('Isolation state conflict: Unknown status after concurrent change. Dispatching cleanup.', [
            'operation_job_id' => $operationJob->id,
            'service_isolation_id' => $serviceIsolation->id,
            'service_id' => $operationJob->service_id,
            'router_id' => $operationJob->router_id,
            'target_address' => $operationJob->target_address,
            'current_status' => $serviceIsolation->status->value,
        ]);

        $this->dispatchReleaseJob($operationJob);
        $this->updateMonitoringStatusesAfterConflict($operationJob, true);
        $this->sendConflictAlert($operationJob, $serviceIsolation, 'unknown_status');
    }

    private function dispatchReleaseJob(ServiceRouterOperationJob $operationJob): void
    {
        try {
            $this->routerJobDispatcher->dispatchRelease($operationJob->service_isolation_id);
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to dispatch release job during conflict resolution.', [
                'operation_job_id' => $operationJob->id,
                'service_isolation_id' => $operationJob->service_isolation_id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function sendConflictAlert(
        ServiceRouterOperationJob $operationJob,
        ?ServiceIsolation $serviceIsolation,
        string $conflictType
    ): void {
        if ($serviceIsolation === null && $operationJob->service_isolation_id !== null) {
            $serviceIsolation = ServiceIsolation::query()->find($operationJob->service_isolation_id);
        }

        try {
            $this->telegramAlertService->queueIsolationConflictAlert(
                $operationJob,
                $serviceIsolation ?? new ServiceIsolation(['status' => ServiceIsolationStatus::Pending]),
                $conflictType,
            );
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to queue Telegram alert for isolation conflict.', [
                'operation_job_id' => $operationJob->id,
                'service_isolation_id' => $operationJob->service_isolation_id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function applyReleaseStateIfNeeded(ServiceRouterOperationJob $operationJob): void
    {
        $serviceIsolation = $operationJob->serviceIsolation?->refresh();

        if ($serviceIsolation === null) {
            return;
        }

        // Only update to Released if currently in ReleasePending state
        if ($serviceIsolation->status !== ServiceIsolationStatus::ReleasePending) {
            $this->logger()->warning('Release state applied but isolation status was not ReleasePending.', [
                'operation_job_id' => $operationJob->id,
                'service_isolation_id' => $serviceIsolation->id,
                'current_status' => $serviceIsolation->status->value,
            ]);

            return;
        }

        // Update isolation status to Released
        $serviceIsolation->update([
            'status' => ServiceIsolationStatus::Released->value,
        ]);

        // Update service status to Active
        $service = $operationJob->service?->refresh();
        if ($service !== null) {
            $service->update([
                'network_status' => ServiceNetworkStatus::Active->value,
                'overall_status' => ServiceOverallStatus::Active->value,
            ]);
        }

        $this->logger()->info('Service isolation released successfully after router confirmation.', [
            'operation_job_id' => $operationJob->id,
            'service_isolation_id' => $serviceIsolation->id,
            'service_id' => $operationJob->service_id,
        ]);
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

        $isRelease = $operationJob->operation_type === ServiceRouterOperationType::Release;

        $fillData = [
            'router_id' => $service->router_id,
            'access_mode' => $service->resolvedAccessMode()->value,
            'isolation_target_type' => $service->resolvedIsolationTargetType()->value,
            'pppoe_username' => $service->operationalPppoeUsername(),
            'static_ip_address' => $service->operationalStaticIpAddress(),
            'static_mac_address' => $service->static_mac_address,
            'queue_name' => $service->static_queue_name,
            'address_list_name' => $operationJob->address_list_name,
            'last_synced_at' => now(),
        ];

        if ($isRelease) {
            $fillData['open_isolation_id'] = null;
            $fillData['isolation_applied'] = false;
            $fillData['address_list_target'] = null;
            $fillData['address_list_found'] = false;
            $fillData['isolation_detected_via'] = null;
            $fillData['isolation_verified_at'] = null;
        } else {
            $fillData['open_isolation_id'] = $operationJob->service_isolation_id;
            $fillData['isolation_applied'] = $addressListFound;
            $fillData['address_list_target'] = $addressListFound ? $operationJob->target_address : null;
            $fillData['address_list_found'] = $addressListFound;
            $fillData['isolation_detected_via'] = $addressListFound ? 'address_list' : null;
            $fillData['isolation_verified_at'] = $addressListFound ? now() : null;
        }

        $operationStatus->fill($fillData);

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
        $customerCode = $operationJob->service?->customer?->customer_code ?? 'unknown';
        $vid = $operationJob->service?->vid?->vid ?? 'unknown';

        return "{$customerCode} | VID-{$vid}";
    }

    /**
     * Build comment substrings to match address-list entries created by legacy isolation flows.
     *
     * Old format: "feralix-billing isolate service:<service_code> isolation:<id>"
     *
     * @return list<string>
     */
    private function buildLegacyCommentPatterns(ServiceRouterOperationJob $operationJob): array
    {
        $patterns = [];

        $serviceCode = $operationJob->service?->service_code;
        if ($serviceCode !== null && $serviceCode !== '') {
            $patterns[] = $serviceCode;
            $patterns[] = "service:{$serviceCode}";
        }

        $isolationId = $operationJob->service_isolation_id;
        if ($isolationId !== null) {
            $patterns[] = "isolation:{$isolationId}";
        }

        return $patterns;
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

    private function markServiceIsolationFailed(ServiceRouterOperationJob $operationJob, string $reason): void
    {
        $serviceIsolation = $operationJob->serviceIsolation?->refresh();

        if ($serviceIsolation === null) {
            return;
        }

        try {
            if ($operationJob->operation_type === ServiceRouterOperationType::Release) {
                $this->serviceIsolationService->markReleaseFailed($serviceIsolation, $reason);
            } else {
                $this->serviceIsolationService->markFailed($serviceIsolation, $reason);
            }

            $this->logger()->warning('ServiceIsolation marked as failed after Mikrotik operation exhausted all retries.', [
                'operation_job_id' => $operationJob->id,
                'service_isolation_id' => $serviceIsolation->id,
                'operation_type' => $operationJob->operation_type?->value,
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            $this->logger()->error('Failed to mark ServiceIsolation as failed.', [
                'operation_job_id' => $operationJob->id,
                'service_isolation_id' => $serviceIsolation->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function updateMonitoringStatusesAfterConflict(
        ServiceRouterOperationJob $operationJob,
        bool $ipIsolatedInRouter
    ): void {
        $service = $operationJob->service?->refresh();

        if ($service === null) {
            return;
        }

        // Update ServiceRouterOperationStatus to reflect the conflict state
        $this->updateRouterOperationStatus($operationJob, $service, $ipIsolatedInRouter);

        // Update ServiceMonitorPppoeStatus if applicable
        $this->updateMonitorPppoeStatus($operationJob, $service, $ipIsolatedInRouter);
    }

    private function updateRouterOperationStatus(
        ServiceRouterOperationJob $operationJob,
        Service $service,
        bool $ipIsolatedInRouter
    ): void {
        try {
            $operationStatus = ServiceRouterOperationStatus::query()
                ->where('service_id', $service->id)
                ->first();

            if ($operationStatus === null) {
                $operationStatus = new ServiceRouterOperationStatus(['service_id' => $service->id]);
            }

            $operationStatus->fill([
                'router_id' => $service->router_id,
                'access_mode' => $service->resolvedAccessMode()->value,
                'isolation_target_type' => $service->resolvedIsolationTargetType()->value,
                'pppoe_username' => $service->operationalPppoeUsername(),
                'static_ip_address' => $service->operationalStaticIpAddress(),
                'static_mac_address' => $service->static_mac_address,
                'queue_name' => $service->static_queue_name,
                'address_list_name' => $operationJob->address_list_name,
                'address_list_found' => $ipIsolatedInRouter,
                'address_list_target' => $ipIsolatedInRouter ? $operationJob->target_address : null,
                'isolation_detected_via' => $ipIsolatedInRouter ? 'address_list_conflict' : null,
                'isolation_verified_at' => $ipIsolatedInRouter ? now() : null,
                'last_synced_at' => now(),
                'isolation_conflict' => true,
                'conflict_resolution' => $ipIsolatedInRouter ? 'release_dispatched' : 'unknown',
            ]);

            if (! $operationStatus->exists || $operationStatus->isDirty()) {
                $operationStatus->save();
            }

            $this->logger()->info('Updated ServiceRouterOperationStatus after isolation conflict.', [
                'operation_job_id' => $operationJob->id,
                'service_id' => $service->id,
                'ip_isolated_in_router' => $ipIsolatedInRouter,
            ]);
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to update ServiceRouterOperationStatus after conflict.', [
                'operation_job_id' => $operationJob->id,
                'service_id' => $service->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function updateMonitorPppoeStatus(
        ServiceRouterOperationJob $operationJob,
        Service $service,
        bool $ipIsolatedInRouter
    ): void {
        $accessMode = $service->resolvedAccessMode();

        // Only relevant for PPPoE mode
        if ($accessMode->value !== 'pppoe') {
            return;
        }

        $pppoeUsername = $service->operationalPppoeUsername();
        if ($pppoeUsername === null) {
            return;
        }

        try {
            $monitorStatus = ServiceMonitorPppoeStatus::query()
                ->where('service_id', $service->id)
                ->where('monitor_pppoe_username', $pppoeUsername)
                ->first();

            if ($monitorStatus === null) {
                $monitorStatus = new ServiceMonitorPppoeStatus([
                    'service_id' => $service->id,
                    'router_id' => $service->router_id,
                    'monitor_pppoe_username' => $pppoeUsername,
                ]);
            }

            $monitorStatus->fill([
                'router_id' => $service->router_id,
                'last_synced_at' => now(),
                'isolation_conflict' => true,
            ]);

            if (! $monitorStatus->exists || $monitorStatus->isDirty()) {
                $monitorStatus->save();
            }

            $this->logger()->info('Updated ServiceMonitorPppoeStatus after isolation conflict.', [
                'operation_job_id' => $operationJob->id,
                'service_id' => $service->id,
                'pppoe_username' => $pppoeUsername,
                'ip_isolated_in_router' => $ipIsolatedInRouter,
            ]);
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to update ServiceMonitorPppoeStatus after conflict.', [
                'operation_job_id' => $operationJob->id,
                'service_id' => $service->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function getJobTries(): int
    {
        return 3;
    }
}
