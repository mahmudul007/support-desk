<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Enums\ActivityType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Events\TicketResolved;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use App\Services\Sla\SlaCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function __construct(
        protected TicketNumberGenerator $numberGenerator,
        protected SlaCalculator $slaCalculator,
        protected ActivityLogger $activityLogger,
    ) {}

    /**
     * Create a new ticket with auto-allocated sequential number and SLA calculations.
     *
     * @param  array{
     *     subject: string,
     *     description: string,
     *     priority: TicketPriority|string,
     *     category_id?: int|null,
     *     created_by?: int|null
     * }  $data
     */
    public function create(array $data, User $creator): Ticket
    {
        $priority = $data['priority'] instanceof TicketPriority
            ? $data['priority']
            : TicketPriority::from((string) $data['priority']);

        return DB::transaction(function () use ($data, $creator, $priority): Ticket {
            $ticketNumber = $this->numberGenerator->next();
            $timestamps = $this->slaCalculator->calculateTimestampsForPriority($priority, now());

            $ticket = Ticket::create([
                'ticket_number' => $ticketNumber,
                'external_ref' => $data['external_ref'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $priority,
                'status' => TicketStatus::Open,
                'category_id' => $data['category_id'] ?? null,
                'created_by' => $data['created_by'] ?? $creator->id,
                'sla_due_at' => $timestamps['sla_due_at'],
                'sla_warning_at' => $timestamps['sla_warning_at'],
            ]);

            $this->activityLogger->log(
                $ticket,
                ActivityType::Created,
                $creator,
                new: $ticket->ticket_number,
                metadata: [
                    'priority' => $priority->value,
                    'sla_due_at' => $timestamps['sla_due_at']->toIso8601String(),
                ]
            );

            event(new TicketCreated($ticket));

            return $ticket;
        });
    }

    /**
     * Update an existing ticket and recalculate SLA if priority changed.
     *
     * @param  array{
     *     subject?: string,
     *     description?: string,
     *     priority?: TicketPriority|string,
     *     category_id?: int|null
     * }  $data
     */
    public function update(Ticket $ticket, array $data, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $actor): Ticket {
            $oldPriority = $ticket->priority;
            $newPriority = isset($data['priority'])
                ? ($data['priority'] instanceof TicketPriority ? $data['priority'] : TicketPriority::from((string) $data['priority']))
                : $oldPriority;

            $updateData = [];

            if (isset($data['subject'])) {
                $updateData['subject'] = $data['subject'];
            }

            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            if (array_key_exists('category_id', $data)) {
                $updateData['category_id'] = $data['category_id'];
            }

            // If priority changed on an active ticket, recalculate SLA deadlines
            if ($newPriority !== $oldPriority) {
                $updateData['priority'] = $newPriority;

                if ($ticket->isOpen()) {
                    $timestamps = $this->slaCalculator->calculateTimestampsForPriority($newPriority, $ticket->created_at ?? now());
                    $updateData['sla_due_at'] = $timestamps['sla_due_at'];
                    $updateData['sla_warning_at'] = $timestamps['sla_warning_at'];
                }
            }

            $ticket->update($updateData);

            if ($newPriority !== $oldPriority) {
                $this->activityLogger->log(
                    $ticket,
                    ActivityType::PriorityChanged,
                    $actor,
                    old: $oldPriority->label(),
                    new: $newPriority->label()
                );
            }

            return $ticket;
        });
    }

    /**
     * Transition ticket status following defined lifecycle state machine.
     *
     * @throws ValidationException
     */
    public function changeStatus(Ticket $ticket, TicketStatus $newStatus, User $actor): bool
    {
        if ($ticket->status === $newStatus) {
            return true;
        }

        $allowedTransitions = $ticket->status->allowedTransitions();

        if (! in_array($newStatus, $allowedTransitions, true) && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition ticket from {$ticket->status->label()} to {$newStatus->label()}.",
            ]);
        }

        return DB::transaction(function () use ($ticket, $newStatus, $actor): bool {
            $oldStatus = $ticket->status;
            $now = now();

            $updateData = [
                'status' => $newStatus,
            ];

            $activityType = ActivityType::StatusChanged;

            if ($newStatus === TicketStatus::Resolved) {
                $updateData['resolved_at'] = $now;
                $activityType = ActivityType::Resolved;
            } elseif ($newStatus === TicketStatus::Closed) {
                $updateData['closed_at'] = $now;
                if (! $ticket->resolved_at) {
                    $updateData['resolved_at'] = $now;
                }
                $activityType = ActivityType::Closed;
            } elseif ($oldStatus === TicketStatus::Resolved && $newStatus === TicketStatus::InProgress) {
                $updateData['resolved_at'] = null;
                $activityType = ActivityType::Reopened;
            }

            $ticket->update($updateData);

            $this->activityLogger->log(
                $ticket,
                $activityType,
                $actor,
                old: $oldStatus->label(),
                new: $newStatus->label()
            );

            if ($newStatus === TicketStatus::Resolved) {
                event(new TicketResolved($ticket));
            }

            return true;
        });
    }
}
