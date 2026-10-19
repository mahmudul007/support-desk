<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_accept_ensures_only_one_agent_wins(): void
    {
        $agent1 = User::factory()->agent()->create(['name' => 'Agent Alpha']);
        $agent2 = User::factory()->agent()->create(['name' => 'Agent Beta']);

        $ticket = Ticket::factory()->create([
            'assigned_agent_id' => null,
            'status' => TicketStatus::Open,
        ]);

        // First agent sends accept request
        $response1 = $this->actingAs($agent1)
            ->postJson(route('tickets.accept', $ticket));

        $response1->assertOk();
        $this->assertSame('Ticket accepted successfully.', $response1->json('message'));

        // Second agent sends accept request for the same ticket concurrently
        $response2 = $this->actingAs($agent2)
            ->postJson(route('tickets.accept', $ticket));

        $response2->assertStatus(409);
        $this->assertStringContainsString('already been accepted', $response2->json('message'));

        // Refresh ticket from DB: must belong to Agent Alpha only
        $ticket->refresh();
        $this->assertEquals($agent1->id, $ticket->assigned_agent_id);
        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertSame(1, $ticket->version);

        // Verify only 1 activity log created
        $this->assertDatabaseCount('ticket_activities', 1);
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'user_id' => $agent1->id,
            'type' => 'assigned',
        ]);
    }

    public function test_auto_assignment_picks_lowest_workload_agent(): void
    {
        // Agent 1 has 3 open tickets
        $agent1 = User::factory()->agent()->create();
        Ticket::factory()->count(3)->assigned($agent1)->create(['status' => TicketStatus::InProgress]);

        // Agent 2 has 1 open ticket
        $agent2 = User::factory()->agent()->create();
        Ticket::factory()->count(1)->assigned($agent2)->create(['status' => TicketStatus::InProgress]);

        $ticket = Ticket::factory()->create([
            'assigned_agent_id' => null,
            'category_id' => null,
            'status' => TicketStatus::Open,
        ]);

        $service = app(TicketAssignmentService::class);
        $assignedAgent = $service->autoAssign($ticket);

        $this->assertNotNull($assignedAgent);
        $this->assertEquals($agent2->id, $assignedAgent->id);

        $ticket->refresh();
        $this->assertEquals($agent2->id, $ticket->assigned_agent_id);
    }

    public function test_auto_assignment_prefers_agent_with_matching_category(): void
    {
        $hardwareCat = Category::factory()->create(['name' => 'Hardware']);
        $billingCat = Category::factory()->create(['name' => 'Billing']);

        $hardwareAgent = User::factory()->agent()->create();
        $hardwareAgent->categories()->attach($hardwareCat);

        $billingAgent = User::factory()->agent()->create();
        $billingAgent->categories()->attach($billingCat);

        $hardwareTicket = Ticket::factory()->create([
            'category_id' => $hardwareCat->id,
            'assigned_agent_id' => null,
        ]);

        $service = app(TicketAssignmentService::class);
        $assigned = $service->autoAssign($hardwareTicket);

        $this->assertNotNull($assigned);
        $this->assertEquals($hardwareAgent->id, $assigned->id);
    }

    public function test_admin_can_manually_assign_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create(['assigned_agent_id' => null]);

        $response = $this->actingAs($admin)->post(route('tickets.assign', $ticket), [
            'assigned_agent_id' => $agent->id,
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertEquals($agent->id, $ticket->assigned_agent_id);
    }
}
