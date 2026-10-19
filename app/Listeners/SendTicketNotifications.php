<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\SlaApproaching;
use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketEscalated;
use App\Events\TicketReplied;
use App\Events\TicketResolved;
use App\Models\User;
use App\Notifications\SlaApproachingNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketEscalatedNotification;
use App\Notifications\TicketRepliedNotification;
use App\Notifications\TicketResolvedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendTicketNotifications implements ShouldQueue
{
    /**
     * Handle TicketCreated event.
     */
    public function handleTicketCreated(TicketCreated $event): void
    {
        $ticket = $event->ticket;

        // 1. Send confirmation to ticket creator
        if ($ticket->creator) {
            $ticket->creator->notify(new TicketCreatedNotification($ticket));
        }

        // 2. If unassigned, notify admins
        if (! $ticket->assigned_agent_id) {
            $admins = User::query()->where('role', UserRole::Admin)->get();
            Notification::send($admins, new TicketCreatedNotification($ticket));
        }
    }

    /**
     * Handle TicketAssigned event.
     */
    public function handleTicketAssigned(TicketAssigned $event): void
    {
        $event->assignedAgent->notify(new TicketAssignedNotification($event->ticket));
    }

    /**
     * Handle TicketReplied event.
     */
    public function handleTicketReplied(TicketReplied $event): void
    {
        $ticket = $event->ticket;
        $reply = $event->reply;

        // If this is an internal note, NEVER notify customers
        if ($reply->is_internal) {
            return;
        }

        // If customer replied, notify assigned agent (or admins if unassigned)
        if ($reply->user->isCustomer()) {
            if ($ticket->assignedAgent) {
                $ticket->assignedAgent->notify(new TicketRepliedNotification($ticket, $reply));
            } else {
                $admins = User::query()->where('role', UserRole::Admin)->get();
                Notification::send($admins, new TicketRepliedNotification($ticket, $reply));
            }

            return;
        }

        // If staff replied, notify customer
        if ($ticket->creator) {
            $ticket->creator->notify(new TicketRepliedNotification($ticket, $reply));
        }
    }

    /**
     * Handle TicketResolved event.
     */
    public function handleTicketResolved(TicketResolved $event): void
    {
        $ticket = $event->ticket;

        if ($ticket->creator) {
            $ticket->creator->notify(new TicketResolvedNotification($ticket));
        }
    }

    /**
     * Handle SlaApproaching event.
     */
    public function handleSlaApproaching(SlaApproaching $event): void
    {
        $ticket = $event->ticket;

        if ($ticket->assignedAgent) {
            $ticket->assignedAgent->notify(new SlaApproachingNotification($ticket));
        } else {
            $admins = User::query()->where('role', UserRole::Admin)->get();
            Notification::send($admins, new SlaApproachingNotification($ticket));
        }
    }

    /**
     * Handle TicketEscalated event.
     */
    public function handleTicketEscalated(TicketEscalated $event): void
    {
        $ticket = $event->ticket;

        // Notify assigned agent
        if ($ticket->assignedAgent) {
            $ticket->assignedAgent->notify(new TicketEscalatedNotification($ticket));
        }

        // Always notify all administrators on SLA breach escalation
        $admins = User::query()->where('role', UserRole::Admin)->get();
        Notification::send($admins, new TicketEscalatedNotification($ticket));
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            TicketCreated::class => 'handleTicketCreated',
            TicketAssigned::class => 'handleTicketAssigned',
            TicketReplied::class => 'handleTicketReplied',
            TicketResolved::class => 'handleTicketResolved',
            SlaApproaching::class => 'handleSlaApproaching',
            TicketEscalated::class => 'handleTicketEscalated',
        ];
    }
}
