<?php

namespace App\Services\Provisioning;

use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceRouterOperationJobStatus;
use App\Enums\ServiceRouterOperationLogAction;
use App\Enums\ServiceRouterOperationType;
use App\Jobs\ProcessServiceRouterOperationJob;
use App\Models\ServiceIsolation;
use App\Models\ServiceRouterOperationJob;
use App\Models\ServiceRouterOperationLog;
use Illuminate\Log\LogManager;
use Throwable;

class ServiceIsolationRouterJobDispatcher
{
    public function __construct(
        private readonly ServiceIsolationRouterTargetResolver $targetResolver,
        private readonly LogManager $logManager,
    ) {}

    public function dispatchIsolation(int $serviceIsolationId): ?ServiceRouterOperationJob
    {
        return $this->dispatch($serviceIsolationId, ServiceRouterOperationType::Isolate);
    }

    public function dispatchRelease(int $serviceIsolationId): ?ServiceRouterOperationJob
    {
        return $this->dispatch($serviceIsolationId, ServiceRouterOperationType::Release);
    }

    private function dispatch(int $serviceIsolationId, ServiceRouterOperationType $operationType): ?ServiceRouterOperationJob
    {
        $serviceIsolation = ServiceIsolation::query()
            ->with([
                'service:id,customer_id,router_id,vid_id,service_code,access_mode,isolation_method,address_list_name,dhcp_pool_start,static_ip_address',
                'service.vid:id,pool_start_ip',
                'service.routerOperationStatus:id,service_id,static_ip_address',
            ])
            ->find($serviceIsolationId);

        if ($serviceIsolation === null || $serviceIsolation->service === null || $serviceIsolation->router_id === null) {
            $this->logger()->warning('Skipping service router operation dispatch because service isolation data is incomplete.', [
                'service_isolation_id' => $serviceIsolationId,
                'operation_type' => $operationType->value,
            ]);

            return null;
        }

        if ($serviceIsolation->service->isolation_method !== ServiceIsolationMethod::AddressList) {
            $this->logger()->info('Skipping service router operation dispatch for non-address-list isolation method.', [
                'service_isolation_id' => $serviceIsolation->id,
                'service_id' => $serviceIsolation->service_id,
                'operation_type' => $operationType->value,
                'isolation_method' => $serviceIsolation->service->isolation_method?->value,
            ]);

            return null;
        }

        try {
            $target = $this->targetResolver->resolve($serviceIsolation);
        } catch (Throwable $throwable) {
            return $this->createFailedJob($serviceIsolation, $operationType, $throwable->getMessage(), [
                'exception' => get_class($throwable),
            ]);
        }

        $queueName = (string) config('automation.queues.network', 'network');

        $operationJob = ServiceRouterOperationJob::query()->create([
            'service_id' => $serviceIsolation->service_id,
            'service_isolation_id' => $serviceIsolation->id,
            'router_id' => $serviceIsolation->router_id,
            'operation_type' => $operationType->value,
            'job_status' => ServiceRouterOperationJobStatus::Pending->value,
            'queue_name' => $queueName,
            'address_list_name' => $target['address_list_name'],
            'target_address' => $target['target_address'],
            'summary' => null,
        ]);

        $this->storeLog(
            $operationJob,
            ServiceRouterOperationLogAction::Queued,
            'Service router operation queued.',
            null,
            ['comment' => $target['comment']],
        );

        ProcessServiceRouterOperationJob::dispatch($operationJob->id)->onQueue($queueName);

        $this->logger()->info('Queued service router operation job.', [
            'operation_job_id' => $operationJob->id,
            'service_isolation_id' => $serviceIsolation->id,
            'service_id' => $serviceIsolation->service_id,
            'router_id' => $serviceIsolation->router_id,
            'operation_type' => $operationType->value,
            'address_list_name' => $target['address_list_name'],
            'target_address' => $target['target_address'],
            'queue_name' => $queueName,
        ]);

        return $operationJob;
    }

    private function createFailedJob(
        ServiceIsolation $serviceIsolation,
        ServiceRouterOperationType $operationType,
        string $message,
        array $context = [],
    ): ServiceRouterOperationJob {
        $operationJob = ServiceRouterOperationJob::query()->create([
            'service_id' => $serviceIsolation->service_id,
            'service_isolation_id' => $serviceIsolation->id,
            'router_id' => $serviceIsolation->router_id,
            'operation_type' => $operationType->value,
            'job_status' => ServiceRouterOperationJobStatus::Failed->value,
            'queue_name' => (string) config('automation.queues.network', 'network'),
            'address_list_name' => $serviceIsolation->address_list_name,
            'target_address' => null,
            'started_at' => now(),
            'finished_at' => now(),
            'summary' => $message,
        ]);

        $this->storeLog(
            $operationJob,
            ServiceRouterOperationLogAction::Failed,
            $message,
            null,
            $context,
        );

        $this->logger()->error('Failed to prepare service router operation job.', [
            'operation_job_id' => $operationJob->id,
            'service_isolation_id' => $serviceIsolation->id,
            'service_id' => $serviceIsolation->service_id,
            'router_id' => $serviceIsolation->router_id,
            'operation_type' => $operationType->value,
            'message' => $message,
            ...$context,
        ]);

        return $operationJob;
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

    private function logger()
    {
        $channel = (string) (config('mikrotik.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }
}
