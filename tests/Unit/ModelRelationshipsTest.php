<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ActivityType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_and_agent_relationship(): void
    {
        $category = Category::factory()->create();
        $agent = User::factory()->agent()->create();

        $agent->categories()->attach($category);

        $this->assertTrue($agent->categories->contains($category));
        $this->assertTrue($category->agents->contains($agent));
    }

    public function test_ticket_relationships(): void
    {
        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();
        $category = Category::factory()->create();

        $ticket = Ticket::factory()->create([
            'created_by' => $customer->id,
            'assigned_agent_id' => $agent->id,
            'category_id' => $category->id,
        ]);

        $this->assertEquals($customer->id, $ticket->creator->id);
        $this->assertEquals($agent->id, $ticket->assignedAgent->id);
        $this->assertEquals($category->id, $ticket->category->id);

        $reply = TicketReply::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
        ]);

        $attachment = Attachment::factory()->create([
            'ticket_id' => $ticket->id,
            'ticket_reply_id' => $reply->id,
            'uploaded_by' => $agent->id,
        ]);

        $activity = TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'type' => ActivityType::Replied,
        ]);

        $this->assertTrue($ticket->replies->contains($reply));
        $this->assertTrue($ticket->attachments->contains($attachment));
        $this->assertTrue($ticket->activities->contains($activity));
    }

    public function test_customer_cannot_see_internal_notes_in_query_scopes(): void
    {
        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

        $publicReply = TicketReply::factory()->create([
            'ticket_id' => $ticket->id,
            'is_internal' => false,
        ]);

        $internalReply = TicketReply::factory()->internal()->create([
            'ticket_id' => $ticket->id,
        ]);

        $repliesForCustomer = TicketReply::query()->visibleTo($customer)->get();

        $this->assertTrue($repliesForCustomer->contains($publicReply));
        $this->assertFalse($repliesForCustomer->contains($internalReply));
    }

    public function test_sla_scopes_on_ticket(): void
    {
        $breachedTicket = Ticket::factory()->breached()->create();
        $normalTicket = Ticket::factory()->create([
            'sla_warning_at' => now()->addHour(),
            'sla_due_at' => now()->addHours(2),
            'status' => TicketStatus::Open,
        ]);

        $this->assertTrue(Ticket::query()->slaBreached()->get()->contains($breachedTicket));
        $this->assertFalse(Ticket::query()->slaBreached()->get()->contains($normalTicket));

        $this->assertTrue(Ticket::query()->slaWithin()->get()->contains($normalTicket));
    }

    public function test_sla_policy_priority_casting(): void
    {
        $policy = SlaPolicy::factory()->create([
            'priority' => TicketPriority::Critical,
            'resolution_minutes' => 120,
        ]);

        $this->assertInstanceOf(TicketPriority::class, $policy->priority);
        $this->assertEquals(TicketPriority::Critical, $policy->priority);
        $this->assertEquals(120, $policy->resolution_minutes);
    }
}
