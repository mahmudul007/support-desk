<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Enums\ActivityType;
use App\Enums\TicketStatus;
use App\Events\TicketReplied;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TicketConversationService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Add a reply or internal note to a ticket with optional attachments.
     *
     * @param  array{
     *     body: string,
     *     is_internal?: bool,
     *     set_waiting_for_customer?: bool
     * }  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function addReply(Ticket $ticket, array $data, User $author, array $files = []): TicketReply
    {
        return DB::transaction(function () use ($ticket, $data, $author, $files): TicketReply {
            $isInternal = false;

            // Only staff members can post internal notes
            if (! $author->isCustomer() && ! empty($data['is_internal'])) {
                $isInternal = true;
            }

            $reply = TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $author->id,
                'body' => $data['body'],
                'is_internal' => $isInternal,
            ]);

            // Save attachments if provided
            if (! empty($files)) {
                $this->attachmentService->storeFiles($ticket, $files, $author, $reply);
            }

            // Automatic status updates based on interaction:
            // 1. If customer replied and ticket was waiting for customer or resolved, move to in_progress
            if ($author->isCustomer() && ($ticket->status === TicketStatus::WaitingForCustomer || $ticket->status === TicketStatus::Resolved)) {
                $oldStatus = $ticket->status;
                $ticket->update([
                    'status' => TicketStatus::InProgress,
                    'resolved_at' => null,
                ]);

                $this->activityLogger->log(
                    $ticket,
                    $oldStatus === TicketStatus::Resolved ? ActivityType::Reopened : ActivityType::StatusChanged,
                    $author,
                    old: $oldStatus->label(),
                    new: TicketStatus::InProgress->label()
                );
            }

            // 2. If staff replied and flagged to wait for customer
            if (! $author->isCustomer() && ! empty($data['set_waiting_for_customer']) && $ticket->isOpen()) {
                $ticket->update(['status' => TicketStatus::WaitingForCustomer]);
                $this->activityLogger->log(
                    $ticket,
                    ActivityType::StatusChanged,
                    $author,
                    old: $ticket->status->label(),
                    new: TicketStatus::WaitingForCustomer->label()
                );
            }

            // Record response metric if this is the first agent response
            if (! $author->isCustomer() && ! $isInternal && $ticket->first_response_at === null) {
                $ticket->update(['first_response_at' => now()]);
            }

            // Log activity
            $this->activityLogger->log(
                $ticket,
                $isInternal ? ActivityType::InternalNote : ActivityType::Replied,
                $author,
                new: $isInternal ? 'Internal Note Added' : 'Public Reply'
            );

            event(new TicketReplied($ticket, $reply));

            return $reply;
        });
    }
}
