<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_download_file_from_own_ticket(): void
    {
        Storage::fake('local');

        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['created_by' => $customer->id]);

        $filePath = 'attachments/test_document.pdf';
        Storage::disk('local')->put($filePath, 'Fake PDF document content');

        $attachment = Attachment::factory()->create([
            'ticket_id' => $ticket->id,
            'path' => $filePath,
            'original_name' => 'test_document.pdf',
        ]);

        $response = $this->actingAs($customer)->get(route('attachments.download', $attachment));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_customer_cannot_download_file_from_another_customer_ticket(): void
    {
        Storage::fake('local');

        $customer1 = User::factory()->customer()->create();
        $customer2 = User::factory()->customer()->create();

        $ticket = Ticket::factory()->create(['created_by' => $customer1->id]);
        $attachment = Attachment::factory()->create([
            'ticket_id' => $ticket->id,
            'path' => 'attachments/secret.pdf',
        ]);

        $response = $this->actingAs($customer2)->get(route('attachments.download', $attachment));

        $response->assertForbidden();
    }

    public function test_downloading_missing_disk_file_returns_404(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $attachment = Attachment::factory()->create([
            'ticket_id' => $ticket->id,
            'path' => 'attachments/does_not_exist.pdf',
        ]);

        $response = $this->actingAs($admin)->get(route('attachments.download', $attachment));

        $response->assertNotFound();
    }
}
