<?php

namespace Tests\Feature\Console;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\TelegramLogStatus;
use App\Enums\TicketAssignmentMode;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Jobs\ProcessQueuedTelegramNotificationsJob;
use App\Jobs\SyncGenieAcsOntJob;
use App\Jobs\SyncMikrotikVidJob;
use App\Jobs\SyncPppoeMonitorJob;
use App\Models\Customer;
use App\Models\Olt;
use App\Models\Ont;
use App\Models\Package;
use App\Models\Router;
use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduledOperationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_existing_sync_commands_for_scheduler_execution(): void
    {
        Queue::fake();

        $mikrotikRouter = $this->createRouter('RTR-MIK-001');
        $monitorRouter = $this->createRouter('RTR-MON-001');
        $this->createService($monitorRouter, 'SVC-MON-001');
        $ont = $this->createOnt();

        $this->artisan('mikrotik:sync-vids', [
            'router' => $mikrotikRouter->id,
            '--queue' => true,
        ])->assertSuccessful();

        $this->artisan('monitor:sync-pppoe', [
            'router' => $monitorRouter->id,
            '--queue' => true,
        ])->assertSuccessful();

        $this->artisan('genieacs:sync-onts', [
            'ont' => $ont->id,
            '--queue' => true,
        ])->assertSuccessful();

        Queue::assertPushed(SyncMikrotikVidJob::class, 1);
        Queue::assertPushed(SyncPppoeMonitorJob::class, 1);
        Queue::assertPushed(SyncGenieAcsOntJob::class, 1);
    }

    public function test_it_processes_queued_telegram_notifications_inline(): void
    {
        $ticket = $this->createTicket();

        $telegramLog = $ticket->telegramLogs()->create([
            'channel' => 'telegram',
            'target' => 'helpdesk',
            'message' => 'Ticket baru',
            'payload' => [
                'event' => 'ticket.created',
            ],
            'status' => TelegramLogStatus::Queued->value,
        ]);

        $this->artisan('notifications:process-telegram', [
            '--limit' => 10,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('processed 1');

        $this->assertDatabaseHas('telegram_logs', [
            'id' => $telegramLog->id,
            'status' => TelegramLogStatus::Processed->value,
        ]);
    }

    public function test_it_queues_telegram_processing_when_requested(): void
    {
        Queue::fake();

        $this->artisan('notifications:process-telegram', [
            '--queue' => true,
            '--limit' => 25,
        ])->assertSuccessful();

        Queue::assertPushed(ProcessQueuedTelegramNotificationsJob::class, 1);
    }

    private function createRouter(string $routerCode): Router
    {
        return Router::query()->create([
            'router_code' => $routerCode,
            'router_name' => 'Router '.$routerCode,
            'router_role' => 'bng',
            'mgmt_ip' => '10.20.20.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_active' => true,
        ]);
    }

    private function createService(Router $router, string $serviceCode): Service
    {
        $customer = Customer::query()->create([
            'customer_code' => 'CUST-'.$serviceCode,
            'full_name' => 'Customer '.$serviceCode,
            'phone' => '081234567890',
            'address' => 'Jl. Monitoring '.$serviceCode,
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);

        $package = Package::query()->create([
            'package_name' => 'Package '.$serviceCode,
            'monthly_price' => 150000,
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'is_active' => true,
        ]);

        return Service::query()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'router_id' => $router->id,
            'service_code' => $serviceCode,
            'monitor_vid' => 100,
            'monitor_pppoe_username' => strtolower('monitor.'.$serviceCode),
            'monitor_pppoe_password' => 'secret-monitor',
            'internet_vid' => 240,
            'subnet_cidr' => '10.0.0.0/24',
            'gateway_ip' => '10.0.0.1',
            'dhcp_pool_start' => '10.0.0.10',
            'dhcp_pool_end' => '10.0.0.50',
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 20,
            'isolation_method' => ServiceIsolationMethod::AddressList->value,
            'address_list_name' => 'FTTH-'.$serviceCode,
            'billing_status' => ServiceBillingStatus::Pending->value,
            'network_status' => ServiceNetworkStatus::Active->value,
            'overall_status' => ServiceOverallStatus::Active->value,
            'activation_date' => '2026-04-01',
        ]);
    }

    private function createOnt(): Ont
    {
        $olt = Olt::query()->create([
            'olt_code' => 'OLT-SCHED-001',
            'olt_name' => 'OLT Scheduler',
            'mgmt_ip' => '10.30.30.1',
            'vendor_name' => 'ZTE',
            'location_name' => 'POP Scheduler',
            'is_active' => true,
        ]);

        return Ont::query()->create([
            'olt_id' => $olt->id,
            'ont_sn' => 'ONT-SCHED-001',
            'ont_name' => 'ONT Scheduler 001',
            'pon_port' => '1/1/1',
            'onu_id' => 1,
            'status' => 'unknown',
        ]);
    }

    private function createTicket(): Ticket
    {
        $customer = Customer::query()->create([
            'customer_code' => 'CUST-TICKET-001',
            'full_name' => 'Customer Ticket',
            'phone' => '081299999999',
            'address' => 'Jl. Ticket Scheduler',
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);

        return Ticket::query()->create([
            'customer_id' => $customer->id,
            'service_id' => null,
            'ticket_number' => 'TCK-20260422-AAAAAA',
            'category' => 'helpdesk',
            'priority' => TicketPriority::High->value,
            'description' => 'Perlu proses notifikasi Telegram.',
            'assigned_technician_id' => null,
            'assignment_mode' => TicketAssignmentMode::Manual->value,
            'status' => TicketStatus::Open->value,
        ]);
    }
}
