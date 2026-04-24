<?php

namespace Tests\Feature\Console;

use App\Support\DatabaseHealthCheckService;
use Illuminate\Console\Command;
use Mockery;
use Tests\TestCase;

class DatabaseHealthCheckCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_reports_a_healthy_database_connection(): void
    {
        $service = Mockery::mock(DatabaseHealthCheckService::class);
        $service->shouldReceive('check')
            ->once()
            ->with(null)
            ->andReturn([
                'ok' => true,
                'connection' => 'mysql',
                'driver' => 'mysql',
                'database' => 'feralix_billing',
                'host' => '127.0.0.1',
                'version' => '8.0.36',
                'latency_ms' => 4.21,
            ]);

        $this->app->instance(DatabaseHealthCheckService::class, $service);

        $this->artisan('db:health-check')
            ->expectsOutputToContain('Database connection is healthy.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_it_reports_a_failed_database_connection(): void
    {
        $service = Mockery::mock(DatabaseHealthCheckService::class);
        $service->shouldReceive('check')
            ->once()
            ->with('mariadb')
            ->andReturn([
                'ok' => false,
                'message' => 'Database connection is unavailable.',
                'reason' => 'host_unreachable',
                'details' => 'The application could not reach the configured database host, port, or socket.',
                'hint' => 'Confirm the database service is running and that DB_HOST, DB_PORT, or DB_SOCKET match the server configuration.',
                'connection' => 'mariadb',
            ]);

        $this->app->instance(DatabaseHealthCheckService::class, $service);

        $this->artisan('db:health-check', [
            '--connection' => 'mariadb',
        ])
            ->expectsOutputToContain('Database connection is unavailable.')
            ->expectsOutputToContain('Reason: host_unreachable')
            ->assertExitCode(Command::FAILURE);
    }
}
