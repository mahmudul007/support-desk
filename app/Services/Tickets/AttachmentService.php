<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Enums\ActivityType;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AttachmentService
{
    public function __construct(
        protected ActivityLogger $activityLogger
    ) {}

    /**
     * Store uploaded files securely on private disk and associate with ticket/reply.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, Attachment>
     */
    public function storeFiles(Ticket $ticket, array $files, User $uploader, ?TicketReply $reply = null): array
    {
        $createdAttachments = [];

        foreach ($files as $file) {
            $extension = $file->getClientOriginalExtension();
            $storedName = Str::uuid().'.'.$extension;
            $directory = "tickets/{$ticket->id}";

            // Store on local private disk (not accessible publicly via symlink)
            $path = $file->storeAs($directory, $storedName, 'local');

            $attachment = Attachment::create([
                'ticket_id' => $ticket->id,
                'ticket_reply_id' => $reply?->id,
                'uploaded_by' => $uploader->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
            ]);

            $this->activityLogger->log(
                $ticket,
                ActivityType::AttachmentAdded,
                $uploader,
                new: $attachment->original_name
            );

            $createdAttachments[] = $attachment;
        }

        return $createdAttachments;
    }
}
