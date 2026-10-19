<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityType: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case Reassigned = 'reassigned';
    case StatusChanged = 'status_changed';
    case PriorityChanged = 'priority_changed';
    case Replied = 'replied';
    case InternalNote = 'internal_note';
    case AttachmentAdded = 'attachment_added';
    case SlaWarning = 'sla_warning';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Reopened = 'reopened';

    /**
     * Get human readable activity title.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Ticket Created',
            self::Assigned => 'Assigned',
            self::Reassigned => 'Reassigned',
            self::StatusChanged => 'Status Changed',
            self::PriorityChanged => 'Priority Changed',
            self::Replied => 'Customer / Agent Replied',
            self::InternalNote => 'Internal Note Added',
            self::AttachmentAdded => 'Attachment Added',
            self::SlaWarning => 'SLA Approaching Warning',
            self::Escalated => 'Ticket Escalated',
            self::Resolved => 'Ticket Resolved',
            self::Closed => 'Ticket Closed',
            self::Reopened => 'Ticket Reopened',
        };
    }
}
