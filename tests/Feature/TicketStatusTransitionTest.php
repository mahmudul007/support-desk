<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_ticket_state_machine_workflow(): void
    {
        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();

        // 1. Initial State: Open
        $ticket = Ticket::factory()->create([
            'created_by' => $customer->id,
            'status' => TicketStatus::Open,
            'assigned_agent_id' => null,
        ]);
        $this->assertSame(TicketStatus::Open, $ticket->status);

        // 2. Agent Claims Ticket -> InProgress
        $this->actingAs($agent)
            ->post(route('tickets.accept', $ticket))
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertSame($agent->id, $ticket->assigned_agent_id);

        // 3. Agent Public Reply with set_waiting_for_customer flag -> WaitingForCustomer
        $this->actingAs($agent)
            ->post(route('tickets.replies.store', $ticket), [
                'body' => 'Could you please provide server error logs?',
                'set_waiting_for_customer' => true,
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(TicketStatus::WaitingForCustomer, $ticket->status);

        // 4. Customer Replies -> Reverts to InProgress
        $this->actingAs($customer)
            ->post(route('tickets.replies.store', $ticket), [
                'body' => 'Here are the requested logs from /var/log/nginx/error.log',
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(TicketStatus::InProgress, $ticket->status);

        // 5. Agent Marks as Resolved -> Resolved
        $this->actingAs($agent)
            ->patch(route('tickets.status', $ticket), [
                'status' => TicketStatus::Resolved->value,
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);

        // 6. Customer Confirms and Closes Ticket -> Closed
        $this->actingAs($customer)
            ->patch(route('tickets.status', $ticket), [
                'status' => TicketStatus::Closed->value,
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(TicketStatus::Closed, $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_customer_replying_to_resolved_ticket_reopens_it(): void
    {
        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();

        $ticket = Ticket::factory()->resolved()->create([
            'created_by' => $customer->id,
            'assigned_agent_id' => $agent->id,
        ]);

        $this->assertSame(TicketStatus::Resolved, $ticket->status);

        // Customer replies that problem persists
        $this->actingAs($customer)
            ->post(route('tickets.replies.store', $ticket), [
                'body' => 'The error reappeared after restarting the container.',
            ]);

        $ticket->refresh();
        $this->assertSame(TicketStatus::InProgress, $ticket->status);
    }
}
