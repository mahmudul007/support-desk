<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\SlaApproachingNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketEscalatedNotification;
use App\Notifications\TicketRepliedNotification;
use App\Notifications\TicketResolvedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_created_notification_structure(): void
    {
        $ticket = Ticket::factory()->create();
        $notification = new TicketCreatedNotification($ticket);

        $channels = $notification->via($ticket->creator);
        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);

        $mail = $notification->toMail($ticket->creator);
        $this->assertStringContainsString($ticket->ticket_number, $mail->subject);

        $data = $notification->toArray($ticket->creator);
        $this->assertSame($ticket->id, $data['ticket_id']);
        $this->assertSame('ticket_created', $data['type']);
    }

    public function test_ticket_assigned_notification_structure(): void
    {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assigned($agent)->create();
        $notification = new TicketAssignedNotification($ticket);

        $data = $notification->toArray($agent);
        $this->assertSame($ticket->id, $data['ticket_id']);
        $this->assertSame('ticket_assigned', $data['type']);
    }

    public function test_ticket_replied_notification_structure(): void
    {
        $ticket = Ticket::factory()->create();
        $reply = TicketReply::factory()->create(['ticket_id' => $ticket->id]);
        $notification = new TicketRepliedNotification($ticket, $reply);

        $data = $notification->toArray($ticket->creator);
        $this->assertSame($reply->id, $data['reply_id']);
        $this->assertSame('ticket_replied', $data['type']);
    }

    public function test_ticket_resolved_notification_structure(): void
    {
        $ticket = Ticket::factory()->resolved()->create();
        $notification = new TicketResolvedNotification($ticket);

        $data = $notification->toArray($ticket->creator);
        $this->assertSame($ticket->id, $data['ticket_id']);
        $this->assertSame('ticket_resolved', $data['type']);
    }

    public function test_sla_approaching_notification_structure(): void
    {
        $ticket = Ticket::factory()->create();
        $notification = new SlaApproachingNotification($ticket);

        $data = $notification->toArray($ticket->creator);
        $this->assertSame($ticket->id, $data['ticket_id']);
        $this->assertSame('sla_approaching', $data['type']);
    }

    public function test_ticket_escalated_notification_structure(): void
    {
        $ticket = Ticket::factory()->escalated(2)->create();
        $notification = new TicketEscalatedNotification($ticket);

        $data = $notification->toArray($ticket->creator);
        $this->assertSame($ticket->id, $data['ticket_id']);
        $this->assertSame('ticket_escalated', $data['type']);
    }
}

