<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\TelegramLogStatus;
use App\Enums\TicketAssignmentMode;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Jobs\SendTicketCreatedTelegramNotificationJob;
use App\Models\Customer;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-22 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_a_ticket_with_auto_assigned_technician_and_queues_telegram_notification(): void
    {
        Queue::fake();

        $customer = $this->createCustomer();
        $technician = $this->createTechnician('TECH-001', 'Teknisi A');

        $response = $this->postJson('/api/v1/admin/tickets', [
            'customer_id' => $customer->id,
            'category' => 'gangguan internet',
            'priority' => TicketPriority::High->value,
            'description' => 'Koneksi internet putus sejak pagi.',
        ]);

        $ticketId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.assigned_technician_id', $technician->id)
            ->assertJsonPath('data.assignment_mode', TicketAssignmentMode::Auto->value)
            ->assertJsonPath('data.status', TicketStatus::Assigned->value)
            ->assertJsonPath('data.telegram_logs.0.status', TelegramLogStatus::Queued->value);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticketId,
            'customer_id' => $customer->id,
            'category' => 'gangguan internet',
            'priority' => TicketPriority::High->value,
            'assigned_technician_id' => $technician->id,
            'assignment_mode' => TicketAssignmentMode::Auto->value,
            'status' => TicketStatus::Assigned->value,
        ]);

        $this->assertDatabaseHas('telegram_logs', [
            'ticket_id' => $ticketId,
            'channel' => 'telegram',
            'status' => TelegramLogStatus::Queued->value,
        ]);

        Queue::assertPushed(SendTicketCreatedTelegramNotificationJob::class, 1);
    }

    public function test_it_rotates_assignment_to_the_technician_with_the_oldest_last_assignment(): void
    {
        Queue::fake();

        $customer = $this->createCustomer();
        $firstTechnician = $this->createTechnician(
            'TECH-001',
            'Teknisi A',
            true,
            '2026-04-22 08:00:00'
        );
        $secondTechnician = $this->createTechnician(
            'TECH-002',
            'Teknisi B',
            true,
            '2026-04-22 08:30:00'
        );

        $firstResponse = $this->postJson('/api/v1/admin/tickets', [
            'customer_id' => $customer->id,
            'category' => 'billing',
            'priority' => TicketPriority::Medium->value,
            'description' => 'Perlu cek tagihan pertama.',
        ]);

        Carbon::setTestNow('2026-04-22 09:05:00');

        $secondResponse = $this->postJson('/api/v1/admin/tickets', [
            'customer_id' => $customer->id,
            'category' => 'network',
            'priority' => TicketPriority::Low->value,
            'description' => 'Butuh follow-up ringan.',
        ]);

        $firstResponse
            ->assertCreated()
            ->assertJsonPath('data.assigned_technician_id', $firstTechnician->id);

        $secondResponse
            ->assertCreated()
            ->assertJsonPath('data.assigned_technician_id', $secondTechnician->id);
    }

    public function test_it_keeps_ticket_open_when_no_active_technician_is_available(): void
    {
        Queue::fake();

        $customer = $this->createCustomer();
        $this->createTechnician('TECH-001', 'Teknisi Nonaktif', false);

        $response = $this->postJson('/api/v1/admin/tickets', [
            'customer_id' => $customer->id,
            'category' => 'general',
            'priority' => TicketPriority::Urgent->value,
            'description' => 'Ada kendala yang belum punya PIC.',
        ]);

        $ticketId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertJsonPath('data.assigned_technician_id', null)
            ->assertJsonPath('data.status', TicketStatus::Open->value);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticketId,
            'assigned_technician_id' => null,
            'status' => TicketStatus::Open->value,
        ]);
    }

    public function test_it_updates_ticket_status_and_queues_telegram_notification(): void
    {
        Queue::fake();

        $customer = $this->createCustomer();
        $ticket = Ticket::query()->create([
            'customer_id' => $customer->id,
            'ticket_number' => 'TCK-STATUS-001',
            'category' => 'network',
            'priority' => TicketPriority::High->value,
            'description' => 'Ticket perlu update status.',
            'assignment_mode' => TicketAssignmentMode::Auto->value,
            'status' => TicketStatus::Open->value,
        ]);

        $response = $this->patchJson("/api/v1/admin/tickets/{$ticket->id}/status", [
            'status' => 'done',
            'notes' => 'Sudah normal.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $ticket->id)
            ->assertJsonPath('data.status', TicketStatus::Resolved->value);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => TicketStatus::Resolved->value,
        ]);

        $this->assertDatabaseHas('telegram_logs', [
            'ticket_id' => $ticket->id,
            'status' => TelegramLogStatus::Queued->value,
        ]);

        Queue::assertPushed(SendTicketCreatedTelegramNotificationJob::class, 1);
    }

    public function test_it_creates_ticket_reply(): void
    {
        Queue::fake();

        $customer = $this->createCustomer();
        $ticket = Ticket::query()->create([
            'customer_id' => $customer->id,
            'ticket_number' => 'TCK-REPLY-001',
            'category' => 'network',
            'priority' => TicketPriority::Medium->value,
            'description' => 'Ticket perlu reply.',
            'assignment_mode' => TicketAssignmentMode::Auto->value,
            'status' => TicketStatus::Open->value,
        ]);

        $response = $this->postJson("/api/v1/admin/tickets/{$ticket->id}/replies", [
            'body' => 'Teknisi sedang menuju lokasi.',
        ]);

        $replyId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertJsonPath('data.ticket_id', $ticket->id)
            ->assertJsonPath('data.body', 'Teknisi sedang menuju lokasi.')
            ->assertJsonPath('data.user_id', auth()->id());

        $this->assertDatabaseHas('ticket_replies', [
            'id' => $replyId,
            'ticket_id' => $ticket->id,
            'body' => 'Teknisi sedang menuju lokasi.',
        ]);

        $this->assertDatabaseHas('telegram_logs', [
            'ticket_id' => $ticket->id,
            'status' => TelegramLogStatus::Queued->value,
        ]);

        $this->assertSame(1, TicketReply::query()->where('ticket_id', $ticket->id)->count());
        Queue::assertPushed(SendTicketCreatedTelegramNotificationJob::class, 1);
    }

    private function createCustomer(string $customerCode = 'CUST-TICKET-001'): Customer
    {
        return Customer::query()->create([
            'customer_code' => $customerCode,
            'full_name' => 'Pelanggan Ticket',
            'phone' => '081234567890',
            'address' => 'Jl. Ticket No. 1',
            'customer_type' => CustomerType::Residential->value,
            'status' => CustomerStatus::Active->value,
        ]);
    }

    private function createTechnician(
        string $technicianCode,
        string $fullName,
        bool $isActive = true,
        ?string $lastAssignedAt = null,
    ): Technician {
        return Technician::query()->create([
            'technician_code' => $technicianCode,
            'full_name' => $fullName,
            'phone' => '081200000000',
            'is_active' => $isActive,
            'last_assigned_at' => $lastAssignedAt,
        ]);
    }
}
