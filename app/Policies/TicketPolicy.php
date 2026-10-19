<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can view any tickets.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the specific ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isAgent()) {
            return $ticket->assigned_agent_id === $user->id || $ticket->assigned_agent_id === null;
        }

        return $ticket->created_by === $user->id;
    }

    /**
     * Determine whether the user can create tickets.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the ticket.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isAgent()) {
            return $ticket->assigned_agent_id === $user->id;
        }

        // Customers can edit subject/description only while ticket is in initial Open state
        return $ticket->created_by === $user->id && $ticket->status === TicketStatus::Open;
    }

    /**
     * Determine whether the user can change status of the ticket.
     */
    public function changeStatus(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isAgent()) {
            return $ticket->assigned_agent_id === $user->id;
        }

        // Customer can close their own ticket if it is currently resolved
        return $ticket->created_by === $user->id && $ticket->status === TicketStatus::Resolved;
    }

    /**
     * Determine whether the user can accept or assign the ticket.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Support agents are permitted to attempt accepting open tickets;
        // The service layer handles race conditions atomically and responds with 409 Conflict.
        if ($user->isAgent()) {
            return $ticket->isOpen();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }
}
