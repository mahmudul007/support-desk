<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\TicketPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected TicketPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TicketPolicy;
    }

    public function test_view_any_is_authorized_for_all_users(): void
    {
        $customer = User::factory()->customer()->create();
        $this->assertTrue($this->policy->viewAny($customer));
    }

    public function test_view_policy_enforces_customer_and_agent_isolation(): void
    {
        $admin = User::factory()->admin()->create();
        $customer1 = User::factory()->customer()->create();
        $customer2 = User::factory()->customer()->create();
        $agent1 = User::factory()->agent()->create();
        $agent2 = User::factory()->agent()->create();

        $ticket = Ticket::factory()->create([
            'created_by' => $customer1->id,
            'assigned_agent_id' => $agent1->id,
        ]);

        $unassignedTicket = Ticket::factory()->create([
            'created_by' => $customer1->id,
            'assigned_agent_id' => null,
        ]);

        // Admin can view any ticket
        $this->assertTrue($this->policy->view($admin, $ticket));

        // Creator can view own ticket
        $this->assertTrue($this->policy->view($customer1, $ticket));

        // Other customer cannot view
        $this->assertFalse($this->policy->view($customer2, $ticket));

        // Assigned agent can view
        $this->assertTrue($this->policy->view($agent1, $ticket));

        // Non-assigned agent cannot view assigned ticket
        $this->assertFalse($this->policy->view($agent2, $ticket));

        // Any agent can view an unassigned ticket
        $this->assertTrue($this->policy->view($agent2, $unassignedTicket));
    }

    public function test_update_policy_rules(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();

        $openTicket = Ticket::factory()->create([
            'created_by' => $customer->id,
            'status' => TicketStatus::Open,
        ]);

        $inProgressTicket = Ticket::factory()->create([
            'created_by' => $customer->id,
            'assigned_agent_id' => $agent->id,
            'status' => TicketStatus::InProgress,
        ]);

        // Admin can update
        $this->assertTrue($this->policy->update($admin, $openTicket));
        $this->assertTrue($this->policy->update($admin, $inProgressTicket));

        // Customer can update while Open
        $this->assertTrue($this->policy->update($customer, $openTicket));

        // Customer cannot update once InProgress
        $this->assertFalse($this->policy->update($customer, $inProgressTicket));

        // Assigned agent can update InProgress
        $this->assertTrue($this->policy->update($agent, $inProgressTicket));
    }

    public function test_change_status_policy_rules(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();

        $resolvedTicket = Ticket::factory()->create([
            'created_by' => $customer->id,
            'assigned_agent_id' => $agent->id,
            'status' => TicketStatus::Resolved,
        ]);

        $openTicket = Ticket::factory()->create([
            'created_by' => $customer->id,
            'status' => TicketStatus::Open,
        ]);

        // Customer can close resolved ticket
        $this->assertTrue($this->policy->changeStatus($customer, $resolvedTicket));

        // Customer cannot change status of open ticket
        $this->assertFalse($this->policy->changeStatus($customer, $openTicket));

        // Agent can change status of assigned ticket
        $this->assertTrue($this->policy->changeStatus($agent, $resolvedTicket));

        // Admin can change status of any ticket
        $this->assertTrue($this->policy->changeStatus($admin, $openTicket));
    }

    public function test_assign_and_delete_policies(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();
        $customer = User::factory()->customer()->create();

        $openTicket = Ticket::factory()->create(['status' => TicketStatus::Open]);
        $closedTicket = Ticket::factory()->create(['status' => TicketStatus::Closed]);

        // Customer cannot assign
        $this->assertFalse($this->policy->assign($customer, $openTicket));

        // Agent can attempt assign on open ticket
        $this->assertTrue($this->policy->assign($agent, $openTicket));

        // Agent cannot assign closed ticket
        $this->assertFalse($this->policy->assign($agent, $closedTicket));

        // Admin can assign
        $this->assertTrue($this->policy->assign($admin, $openTicket));

        // Only admin can delete
        $this->assertTrue($this->policy->delete($admin, $openTicket));
        $this->assertFalse($this->policy->delete($agent, $openTicket));
        $this->assertFalse($this->policy->delete($customer, $openTicket));
    }
}
