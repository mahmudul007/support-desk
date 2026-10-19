<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SlaApproachingNotification;
use App\Notifications\TicketEscalatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SlaEscalationAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_escalate_command_processes_approaching_tickets_and_is_idempotent(): void
    {
        Notification::fake();

        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assigned($agent)->create([
            'status' => TicketStatus::Open,
            'sla_warning_at' => now()->subMinute(),
            'sla_due_at' => now()->addHour(),
            'approaching_notified_at' => null,
        ]);

        // First run: should trigger warning
        $this->artisan('tickets:escalate-sla')
            ->expectsOutputToContain('Warnings sent: 1')
            ->assertSuccessful();

        $ticket->refresh();
        $this->assertNotNull($ticket->approaching_notified_at);

        Notification::assertSentTo($agent, SlaApproachingNotification::class);

        // Second run: should be completely idempotent (0 warnings)
        $this->artisan('tickets:escalate-sla')
            ->expectsOutputToContain('Warnings sent: 0')
            ->assertSuccessful();
    }

    public function test_escalate_command_escalates_breached_tickets_to_admin_and_agent(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();

        $breachedTicket = Ticket::factory()->assigned($agent)->create([
            'status' => TicketStatus::InProgress,
            'sla_due_at' => now()->subMinutes(10),
            'escalated_at' => null,
            'escalation_count' => 0,
        ]);

        $this->artisan('tickets:escalate-sla')
            ->expectsOutputToContain('Tickets escalated: 1')
            ->assertSuccessful();

        $breachedTicket->refresh();
        $this->assertNotNull($breachedTicket->breached_at);
        $this->assertNotNull($breachedTicket->escalated_at);
        $this->assertSame(1, $breachedTicket->escalation_count);

        Notification::assertSentTo($agent, TicketEscalatedNotification::class);
        Notification::assertSentTo($admin, TicketEscalatedNotification::class);

        // Subsequent run does not re-escalate
        $this->artisan('tickets:escalate-sla')
            ->expectsOutputToContain('Tickets escalated: 0')
            ->assertSuccessful();
    }

    public function test_resolved_tickets_are_never_escalated(): void
    {
        Notification::fake();

        Ticket::factory()->resolved()->create([
            'sla_due_at' => now()->subDay(),
            'escalated_at' => null,
        ]);

        $this->artisan('tickets:escalate-sla')
            ->expectsOutputToContain('Tickets escalated: 0')
            ->assertSuccessful();
    }
}
