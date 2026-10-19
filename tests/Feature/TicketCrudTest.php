<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_ticket(): void
    {
        $customer = User::factory()->customer()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($customer)->post('/tickets', [
            'subject' => 'Printer not working',
            'description' => 'The network printer on floor 2 is unresponsive.',
            'priority' => TicketPriority::High->value,
            'category_id' => $category->id,
        ]);

        $ticket = Ticket::first();

        $this->assertNotNull($ticket);
        $this->assertSame('Printer not working', $ticket->subject);
        $this->assertSame(TicketPriority::High, $ticket->priority);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertNotNull($ticket->ticket_number);
        $this->assertNotNull($ticket->sla_due_at);
        $this->assertNotNull($ticket->sla_warning_at);

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'type' => 'created',
        ]);
    }

    public function test_customer_cannot_view_another_customers_ticket(): void
    {
        $customer1 = User::factory()->customer()->create();
        $customer2 = User::factory()->customer()->create();

        $ticket = Ticket::factory()->create(['created_by' => $customer1->id]);

        $response = $this->actingAs($customer2)->get(route('tickets.show', $ticket));

        $response->assertForbidden();
    }

    public function test_agent_can_view_unassigned_ticket_and_their_assigned_ticket(): void
    {
        $agent1 = User::factory()->agent()->create();
        $agent2 = User::factory()->agent()->create();

        $unassignedTicket = Ticket::factory()->create(['assigned_agent_id' => null]);
        $assignedToAgent1 = Ticket::factory()->create(['assigned_agent_id' => $agent1->id]);
        $assignedToAgent2 = Ticket::factory()->create(['assigned_agent_id' => $agent2->id]);

        // Agent1 can view unassigned and their own
        $this->actingAs($agent1)->get(route('tickets.show', $unassignedTicket))->assertOk();
        $this->actingAs($agent1)->get(route('tickets.show', $assignedToAgent1))->assertOk();

        // Agent1 cannot view ticket assigned to Agent2
        $this->actingAs($agent1)->get(route('tickets.show', $assignedToAgent2))->assertForbidden();
    }

    public function test_admin_can_view_any_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($admin)->get(route('tickets.show', $ticket))->assertOk();
    }

    public function test_authorized_agent_can_change_ticket_status(): void
    {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assigned($agent)->create([
            'status' => TicketStatus::InProgress,
        ]);

        $response = $this->actingAs($agent)->patch(route('tickets.status', $ticket), [
            'status' => TicketStatus::Resolved->value,
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $ticket->refresh();

        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'type' => 'resolved',
        ]);
    }

    public function test_updating_priority_recalculates_sla_deadlines(): void
    {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assigned($agent)->create([
            'priority' => TicketPriority::Low,
            'status' => TicketStatus::InProgress,
        ]);

        $initialDue = $ticket->sla_due_at;

        // Upgrade priority to Critical (2h)
        $this->actingAs($agent)->put(route('tickets.update', $ticket), [
            'priority' => TicketPriority::Critical->value,
        ]);

        $ticket->refresh();

        $this->assertSame(TicketPriority::Critical, $ticket->priority);
        $this->assertNotEquals($initialDue->toIso8601String(), $ticket->sla_due_at->toIso8601String());

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'type' => 'priority_changed',
        ]);
    }
}
