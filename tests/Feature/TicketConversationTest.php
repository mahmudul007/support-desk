<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_post_public_reply(): void
    {
        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

        $response = $this->actingAs($customer)
            ->post(route('tickets.replies.store', $ticket), [
                'body' => 'I restarted the machine, but the error persists.',
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'body' => 'I restarted the machine, but the error persists.',
            'is_internal' => false,
        ]);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'type' => 'replied',
        ]);
    }

    public function test_customer_is_prohibited_from_posting_internal_note(): void
    {
        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

        $response = $this->actingAs($customer)
            ->post(route('tickets.replies.store', $ticket), [
                'body' => 'Trying to post internal note as customer',
                'is_internal' => true,
            ]);

        $response->assertSessionHasErrors(['is_internal']);
        $this->assertDatabaseCount('ticket_replies', 0);
    }

    public function test_support_agent_can_post_internal_note(): void
    {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assigned($agent)->create();

        $response = $this->actingAs($agent)
            ->post(route('tickets.replies.store', $ticket), [
                'body' => 'Internal investigation: Database deadlock observed at 14:02.',
                'is_internal' => true,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'is_internal' => true,
        ]);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'type' => 'internal_note',
        ]);
    }

    public function test_internal_notes_are_strictly_hidden_from_customer_views(): void
    {
        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assigned($agent)->create(['created_by' => $customer->id]);

        // Create public reply and private internal note
        TicketReply::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'body' => 'Public agent reply visible to all.',
            'is_internal' => false,
        ]);

        TicketReply::factory()->internal()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'body' => 'TOP SECRET: Internal diagnostic notes only for agents.',
        ]);

        // Customer visits ticket page
        $response = $this->actingAs($customer)->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Public agent reply visible to all.');
        $response->assertDontSee('TOP SECRET: Internal diagnostic notes only for agents.');

        // Agent visits ticket page
        $agentResponse = $this->actingAs($agent)->get(route('tickets.show', $ticket));

        $agentResponse->assertOk();
        $agentResponse->assertSee('Public agent reply visible to all.');
        $agentResponse->assertSee('TOP SECRET: Internal diagnostic notes only for agents.');
    }

    public function test_customer_reply_auto_transitions_waiting_for_customer_to_in_progress(): void
    {
        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assigned($agent)->create([
            'created_by' => $customer->id,
            'status' => TicketStatus::WaitingForCustomer,
        ]);

        $this->actingAs($customer)->post(route('tickets.replies.store', $ticket), [
            'body' => 'Here is the requested additional information.',
        ]);

        $ticket->refresh();
        $this->assertSame(TicketStatus::InProgress, $ticket->status);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'type' => 'status_changed',
            'old_value' => TicketStatus::WaitingForCustomer->label(),
            'new_value' => TicketStatus::InProgress->label(),
        ]);
    }

    public function test_attachment_upload_and_authorized_download(): void
    {
        Storage::fake('local');

        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

        $file = UploadedFile::fake()->create('error_log.txt', 50, 'text/plain');

        $response = $this->actingAs($customer)
            ->post(route('tickets.replies.store', $ticket), [
                'body' => 'Attached is the crash log.',
                'attachments' => [$file],
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $attachment = Attachment::first();
        $this->assertNotNull($attachment);
        $this->assertSame('error_log.txt', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);

        // Customer can download their attachment
        $downloadResponse = $this->actingAs($customer)
            ->get(route('attachments.download', $attachment));

        $downloadResponse->assertOk();
    }

    public function test_customer_cannot_download_attachment_attached_to_internal_note(): void
    {
        Storage::fake('local');

        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assigned($agent)->create(['created_by' => $customer->id]);

        $internalReply = TicketReply::factory()->internal()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
        ]);

        $attachment = Attachment::factory()->create([
            'ticket_id' => $ticket->id,
            'ticket_reply_id' => $internalReply->id,
            'uploaded_by' => $agent->id,
        ]);

        Storage::disk('local')->put($attachment->path, 'confidential notes');

        // Customer attempt to download should be forbidden (403)
        $this->actingAs($customer)
            ->get(route('attachments.download', $attachment))
            ->assertForbidden();

        // Agent can download
        $this->actingAs($agent)
            ->get(route('attachments.download', $attachment))
            ->assertOk();
    }
}
