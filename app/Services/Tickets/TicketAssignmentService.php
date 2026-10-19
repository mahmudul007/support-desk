<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Enums\ActivityType;
use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketAssignmentService
{
    public function __construct(
        protected ActivityLogger $activityLogger
    ) {}

    /**
     * Atomically accept an unassigned ticket by an agent.
     * Prevents race conditions by using a single atomic conditional database update.
     */
    public function accept(Ticket $ticket, User $agent): bool
    {
        return DB::transaction(function () use ($ticket, $agent): bool {
            // Atomic conditional update: only succeeds if assigned_agent_id is strictly NULL
            $affected = Ticket::query()
                ->whereKey($ticket->id)
                ->whereNull('assigned_agent_id')
                ->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])
                ->update([
                    'assigned_agent_id' => $agent->id,
                    'assigned_at' => now(),
                    'status' => TicketStatus::InProgress,
                    'version' => DB::raw('version + 1'),
                ]);

            if ($affected === 0) {
                return false;
            }

            $agent->update(['last_assigned_at' => now()]);

            $this->activityLogger->log(
                $ticket,
                ActivityType::Assigned,
                $agent,
                new: $agent->name
            );

            event(new TicketAssigned($ticket->fresh(), $agent));

            return true;
        });
    }

    /**
     * Manually assign or reassign a ticket to a specific agent.
     *
     * @throws ValidationException
     */
    public function assign(Ticket $ticket, User $agent, ?User $actor = null): bool
    {
        if (! $agent->isAgent()) {
            throw ValidationException::withMessages([
                'assigned_agent_id' => 'The selected user is not a support agent.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $agent, $actor): bool {
            $isReassignment = $ticket->assigned_agent_id !== null && $ticket->assigned_agent_id !== $agent->id;
            $oldAgentName = $ticket->assignedAgent?->name;

            $ticket->assigned_agent_id = $agent->id;
            $ticket->assigned_at = now();
            if ($ticket->status === TicketStatus::Open) {
                $ticket->status = TicketStatus::InProgress;
            }
            $ticket->version = ((int) $ticket->version) + 1;
            $ticket->save();

            $agent->update(['last_assigned_at' => now()]);

            $this->activityLogger->log(
                $ticket,
                $isReassignment ? ActivityType::Reassigned : ActivityType::Assigned,
                $actor,
                old: $oldAgentName,
                new: $agent->name
            );

            event(new TicketAssigned($ticket->fresh(), $agent));

            return true;
        });
    }

    /**
     * Automatically assign ticket to best available agent using
     * category-aware least-workload strategy with round-robin tie-breaking.
     */
    public function autoAssign(Ticket $ticket): ?User
    {
        return DB::transaction(function () use ($ticket): ?User {
            // Find candidate agents (must be available)
            $candidatesQuery = User::query()->availableAgents();

            // If ticket has a category, prefer agents with that skill
            if ($ticket->category_id) {
                $categoryCandidatesCount = (clone $candidatesQuery)
                    ->whereHas('categories', fn (Builder $q) => $q->where('categories.id', $ticket->category_id))
                    ->count();

                if ($categoryCandidatesCount > 0) {
                    $candidatesQuery->whereHas('categories', fn (Builder $q) => $q->where('categories.id', $ticket->category_id));
                }
            }

            // Exclude agents who reached their max_open_tickets cap
            // Annotate candidate agents with active ticket count
            $candidates = $candidatesQuery
                ->withCount([
                    'assignedTickets as open_tickets_count' => function (Builder $query): void {
                        $query->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed]);
                    },
                ])
                ->get()
                ->filter(function (User $agent): bool {
                    if ($agent->max_open_tickets !== null && $agent->open_tickets_count >= $agent->max_open_tickets) {
                        return false;
                    }

                    return true;
                });

            if ($candidates->isEmpty()) {
                return null;
            }

            // Sort: 1) Lowest workload (least open tickets), 2) Oldest last_assigned_at (round-robin)
            $bestAgent = $candidates->sort(function (User $a, User $b): int {
                if ($a->open_tickets_count !== $b->open_tickets_count) {
                    return $a->open_tickets_count <=> $b->open_tickets_count;
                }

                // If never assigned, assign first
                if ($a->last_assigned_at === null && $b->last_assigned_at !== null) {
                    return -1;
                }
                if ($b->last_assigned_at === null && $a->last_assigned_at !== null) {
                    return 1;
                }

                return ($a->last_assigned_at?->timestamp ?? 0) <=> ($b->last_assigned_at?->timestamp ?? 0);
            })->first();

            if (! $bestAgent) {
                return null;
            }

            // Perform assignment
            $this->assign($ticket, $bestAgent, null);

            return $bestAgent;
        });
    }
}
