<?php

namespace App\Jobs;

use App\Services\Provisioning\ServiceIsolationRouterExecutionService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessServiceRouterOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithAutomationLogging, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(private readonly int $operationJobId)
    {
        $this->afterCommit();
        $this->onQueue(config('automation.queues.network', 'network'));
    }

    public function handle(ServiceIsolationRouterExecutionService $executionService): void
    {
        $this->logAutomationStarted('network.process_service_router_operation', [
            'operation_job_id' => $this->operationJobId,
        ]);

        $executionService->execute($this->operationJobId);

        $this->logAutomationFinished('network.process_service_router_operation', [
            'operation_job_id' => $this->operationJobId,
        ]);
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function failed(Throwable $exception): void
    {
        $this->logAutomationFailed('network.process_service_router_operation', $exception, [
            'operation_job_id' => $this->operationJobId,
        ]);
    }
}
