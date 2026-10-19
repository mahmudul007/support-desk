<?php

declare(strict_types=1);

namespace App\Services\Activity;

use App\Enums\ActivityType;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;

class ActivityLogger
{
    /**
     * Log an immutable audit entry for a ticket event.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        Ticket $ticket,
        ActivityType $type,
        ?User $actor = null,
        ?string $old = null,
        ?string $new = null,
        ?array $metadata = null
    ): TicketActivity {
        return TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'old_value' => $old,
            'new_value' => $new,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
