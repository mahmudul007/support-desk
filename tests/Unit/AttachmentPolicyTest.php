<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Policies\AttachmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected AttachmentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AttachmentPolicy;
    }

    public function test_customer_can_download_public_attachment_on_own_ticket(): void
    {
        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['created_by' => $customer->id]);
        $attachment = Attachment::factory()->create(['ticket_id' => $ticket->id]);

        $this->assertTrue($this->policy->download($customer, $attachment));
    }

    public function test_customer_cannot_download_attachment_from_another_customer_ticket(): void
    {
        $customer1 = User::factory()->customer()->create();
        $customer2 = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['created_by' => $customer1->id]);
        $attachment = Attachment::factory()->create(['ticket_id' => $ticket->id]);

        $this->assertFalse($this->policy->download($customer2, $attachment));
    }

    public function test_customer_cannot_download_attachment_from_internal_note(): void
    {
        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

        $internalReply = TicketReply::factory()->internal()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
        ]);

        $attachment = Attachment::factory()->create([
            'ticket_id' => $ticket->id,
            'ticket_reply_id' => $internalReply->id,
        ]);

        // Customer forbidden from internal attachment even on own ticket
        $this->assertFalse($this->policy->download($customer, $attachment));

        // Agent can download it
        $this->assertTrue($this->policy->download($agent, $attachment));
    }
}
