<?php

namespace Tests\Unit\Provisioning;

use App\Enums\ServiceIsolationStatus;
use App\Enums\ServiceRouterOperationType;
use App\Models\Customer;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceIsolation;
use App\Models\ServiceRouterOperationJob;
use App\Models\ServiceRouterOperationStatus;
use App\Models\Vid;
use App\Services\Helpdesk\TelegramAlertService;
use App\Services\Mikrotik\MikrotikAddressListService;
use App\Services\Provisioning\ServiceIsolationRouterExecutionService;
use App\Services\Provisioning\ServiceIsolationRouterJobDispatcher;
use App\Services\Provisioning\ServiceIsolationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\LogManager;
use Mockery;
use Tests\TestCase;

class ServiceIsolationRouterExecutionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServiceIsolationRouterExecutionService $service;
    private MikrotikAddressListService $addressListService;
    private ServiceIsolationService $serviceIsolationService;
    private ServiceIsolationRouterJobDispatcher $routerJobDispatcher;
    private TelegramAlertService $telegramAlertService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addressListService = Mockery::mock(MikrotikAddressListService::class);
        $this->serviceIsolationService = Mockery::mock(ServiceIsolationService::class);
        $this->routerJobDispatcher = Mockery::mock(ServiceIsolationRouterJobDispatcher::class);
        $this->telegramAlertService = Mockery::mock(TelegramAlertService::class);

        $logManager = Mockery::mock(LogManager::class);
        $logChannel = Mockery::mock();
        $logChannel->shouldReceive('info')->andReturnSelf();
        $logChannel->shouldReceive('error')->andReturnSelf();
        $logChannel->shouldReceive('warning')->andReturnSelf();
        $logManager->shouldReceive('channel')->andReturn($logChannel);

        $this->service = new ServiceIsolationRouterExecutionService(
            $this->addressListService,
            $this->serviceIsolationService,
            $this->routerJobDispatcher,
            $this->telegramAlertService,
            $logManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createRouter(): Router
    {
        return Router::create([
            'router_code' => 'RTR-' . uniqid(),
            'router_name' => 'Test Router',
            'name' => 'Test Router',
            'host' => '192.168.1.1',
            'mgmt_ip' => '192.168.1.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'password',
            'is_active' => true,
            'status' => 'active',
            'router_role' => 'distribution',
        ]);
    }

    private function createServiceIsolation(array $attributes = []): ServiceIsolation
    {
        $customer = Customer::factory()->create();
        $router = $this->createRouter();
        $vid = Vid::factory()->create();
        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'router_id' => $router->id,
            'vid_id' => $vid->id,
        ]);

        return ServiceIsolation::create(array_merge([
            'service_id' => $service->id,
            'router_id' => $router->id,
            'status' => ServiceIsolationStatus::Applied,
        ], $attributes));
    }

    private function createServiceRouterOperationJob(array $overrides = []): ServiceRouterOperationJob
    {
        $customer = Customer::factory()->create();
        $router = $this->createRouter();
        $vid = Vid::factory()->create();
        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'router_id' => $router->id,
            'vid_id' => $vid->id,
        ]);

        $defaults = [
            'service_id' => $service->id,
            'router_id' => $router->id,
            'address_list_name' => 'test-isolate-list',
            'target_address' => '192.168.1.100',
            'operation_type' => ServiceRouterOperationType::Isolate->value,
            'service_isolation_id' => null,
        ];

        return ServiceRouterOperationJob::create(array_merge($defaults, $overrides));
    }

    public function test_sync_router_operation_status_clears_isolation_fields_on_release(): void
    {
        $operationJob = $this->createServiceRouterOperationJob([
            'operation_type' => ServiceRouterOperationType::Release->value,
            'address_list_name' => 'test-release-list',
        ]);

        $service = $operationJob->service;
        $service->routerOperationStatus()->updateOrCreate(
            ['service_id' => $service->id],
            [
                'open_isolation_id' => 999,
                'isolation_applied' => true,
                'address_list_found' => true,
                'address_list_target' => '192.168.1.100',
                'isolation_detected_via' => 'address_list',
                'isolation_verified_at' => now(),
            ]
        );

        $this->service->syncRouterOperationStatus($operationJob, false);

        $status = $service->routerOperationStatus->fresh();

        $this->assertNull($status->open_isolation_id);
        $this->assertFalse($status->isolation_applied);
        $this->assertFalse($status->address_list_found);
        $this->assertNull($status->address_list_target);
        $this->assertNull($status->isolation_detected_via);
        $this->assertNull($status->isolation_verified_at);
    }

    public function test_sync_router_operation_status_sets_isolation_fields_on_isolate(): void
    {
        $isolation = $this->createServiceIsolation();

        $operationJob = $this->createServiceRouterOperationJob([
            'operation_type' => ServiceRouterOperationType::Isolate->value,
            'service_isolation_id' => $isolation->id,
            'address_list_name' => 'test-isolate-list',
        ]);

        $service = $operationJob->service;

        $this->service->syncRouterOperationStatus($operationJob, true);

        $status = $service->routerOperationStatus->fresh();

        $this->assertEquals($isolation->id, $status->open_isolation_id);
        $this->assertTrue($status->isolation_applied);
        $this->assertTrue($status->address_list_found);
        $this->assertEquals('192.168.1.100', $status->address_list_target);
        $this->assertEquals('address_list', $status->isolation_detected_via);
        $this->assertNotNull($status->isolation_verified_at);
    }

    public function test_sync_router_operation_status_sets_open_isolation_id_on_isolate(): void
    {
        $isolation = $this->createServiceIsolation();

        $operationJob = $this->createServiceRouterOperationJob([
            'operation_type' => ServiceRouterOperationType::Isolate->value,
            'service_isolation_id' => $isolation->id,
        ]);

        $service = $operationJob->service;
        $service->routerOperationStatus()->updateOrCreate(
            ['service_id' => $service->id],
            [
                'open_isolation_id' => null,
            ]
        );

        $this->service->syncRouterOperationStatus($operationJob, true);

        $status = $service->routerOperationStatus->fresh();

        $this->assertEquals($isolation->id, $status->open_isolation_id);
        $this->assertTrue($status->isolation_applied);
    }

    public function test_concurrent_state_change_when_status_is_released_dispatches_release_job(): void
    {
        $isolation = $this->createServiceIsolation([
            'status' => ServiceIsolationStatus::Released,
        ]);

        $operationJob = $this->createServiceRouterOperationJob([
            'operation_type' => ServiceRouterOperationType::Isolate->value,
            'service_isolation_id' => $isolation->id,
        ]);

        // Mock the service to throw ValidationException (simulating concurrent state change)
        $this->serviceIsolationService
            ->shouldReceive('markApplied')
            ->andThrow(\Illuminate\Validation\ValidationException::withMessages(['status' => 'Already changed']));

        $this->routerJobDispatcher
            ->shouldReceive('dispatchRelease')
            ->with($isolation->id)
            ->once();

        $this->telegramAlertService
            ->shouldReceive('queueIsolationConflictAlert')
            ->once();

        // Execute applyIsolationStateIfNeeded which should handle the conflict
        $this->service->applyIsolationStateIfNeeded($operationJob);

        // Verify release job was dispatched via mock expectation
    }

    public function test_concurrent_state_change_when_status_is_applied_does_not_dispatch_release(): void
    {
        $isolation = $this->createServiceIsolation([
            'status' => ServiceIsolationStatus::Applied,
        ]);

        $operationJob = $this->createServiceRouterOperationJob([
            'operation_type' => ServiceRouterOperationType::Isolate->value,
            'service_isolation_id' => $isolation->id,
        ]);

        // Mock the service to throw ValidationException
        $this->serviceIsolationService
            ->shouldReceive('markApplied')
            ->andThrow(\Illuminate\Validation\ValidationException::withMessages(['status' => 'Already applied']));

        // RouterJobDispatcher should NOT be called for Applied status
        $this->routerJobDispatcher
            ->shouldNotReceive('dispatchRelease');

        $this->service->applyIsolationStateIfNeeded($operationJob);
    }

    public function test_concurrent_state_change_when_status_is_failed_dispatches_release_job(): void
    {
        $isolation = $this->createServiceIsolation([
            'status' => ServiceIsolationStatus::Failed,
        ]);

        $operationJob = $this->createServiceRouterOperationJob([
            'operation_type' => ServiceRouterOperationType::Isolate->value,
            'service_isolation_id' => $isolation->id,
        ]);

        $this->serviceIsolationService
            ->shouldReceive('markApplied')
            ->andThrow(\Illuminate\Validation\ValidationException::withMessages(['status' => 'Failed']));

        $this->routerJobDispatcher
            ->shouldReceive('dispatchRelease')
            ->with($isolation->id)
            ->once();

        $this->telegramAlertService
            ->shouldReceive('queueIsolationConflictAlert')
            ->once();

        $this->service->applyIsolationStateIfNeeded($operationJob);
    }
}