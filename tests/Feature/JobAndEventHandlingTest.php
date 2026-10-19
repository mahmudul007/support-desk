<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\SlaApproaching;
use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketEscalated;
use App\Events\TicketReplied;
use App\Events\TicketResolved;
use App\Jobs\ProcessTicketImport;
use App\Models\ImportRun;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\SlaApproachingNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketEscalatedNotification;
use App\Notifications\TicketRepliedNotification;
use App\Notifications\TicketResolvedNotification;
use App\Services\Import\TicketImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class JobAndEventHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_ticket_import_job_calls_import_service(): void
    {
        $importRun = ImportRun::factory()->create();

        $mockService = Mockery::mock(TicketImportService::class);
        $mockService->shouldReceive('process')
            ->once()
            ->with(Mockery::on(fn ($run) => $run->id === $importRun->id));

        $this->app->instance(TicketImportService::class, $mockService);

        $job = new ProcessTicketImport($importRun);
        $job->handle($mockService);
    }

    public function test_send_ticket_notifications_subscriber_dispatches_notifications(): void
    {
        Notification::fake();

        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();

        $ticket = Ticket::factory()->assigned($agent)->create([
            'created_by' => $customer->id,
        ]);
        $reply = TicketReply::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'is_internal' => false,
        ]);

        // 1. TicketCreated event
        event(new TicketCreated($ticket));
        Notification::assertSentTo($customer, TicketCreatedNotification::class);

        // 2. TicketAssigned event
        event(new TicketAssigned($ticket, $agent));
        Notification::assertSentTo($agent, TicketAssignedNotification::class);

        // 3. TicketReplied event (agent replied -> customer notified)
        event(new TicketReplied($ticket, $reply));
        Notification::assertSentTo($customer, TicketRepliedNotification::class);

        // 4. TicketResolved event
        event(new TicketResolved($ticket));
        Notification::assertSentTo($customer, TicketResolvedNotification::class);

        // 5. SlaApproaching event
        event(new SlaApproaching($ticket));
        Notification::assertSentTo($agent, SlaApproachingNotification::class);

        // 6. TicketEscalated event
        $admin = User::factory()->admin()->create();
        event(new TicketEscalated($ticket, 1));
        Notification::assertSentTo($admin, TicketEscalatedNotification::class);
    }
}
