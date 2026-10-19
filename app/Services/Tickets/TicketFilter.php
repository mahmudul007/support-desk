<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TicketFilter
{
    /**
     * Apply filter criteria to the ticket query builder.
     *
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    public function apply(Builder $query, array $filters): Builder
    {
        // 1. Ticket Number prefix filter (efficient index seek)
        if (! empty($filters['ticket_number'])) {
            $ticketNumber = trim((string) $filters['ticket_number']);
            $query->where('ticket_number', 'LIKE', "{$ticketNumber}%");
        }

        // 2. Keyword Search
        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $query->whereFullText(['subject', 'description'], $keyword);
            } else {
                $query->where(function (Builder $q) use ($keyword): void {
                    $q->where('subject', 'LIKE', "%{$keyword}%")
                        ->orWhere('description', 'LIKE', "%{$keyword}%");
                });
            }
        }

        // 3. Status filter
        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $query->whereIn('status', $statuses);
        }

        // 4. Priority filter
        if (! empty($filters['priority'])) {
            $priorities = is_array($filters['priority']) ? $filters['priority'] : [$filters['priority']];
            $query->whereIn('priority', $priorities);
        }

        // 5. Category filter
        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        // 6. Assigned Agent filter
        if (! empty($filters['assigned_agent_id'])) {
            if ($filters['assigned_agent_id'] === 'unassigned') {
                $query->whereNull('assigned_agent_id');
            } else {
                $query->where('assigned_agent_id', (int) $filters['assigned_agent_id']);
            }
        }

        // 7. Customer / Creator filter
        if (! empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        // 8. SLA status filter
        if (! empty($filters['sla_status'])) {
            match ($filters['sla_status']) {
                'breached' => $query->slaBreached(),
                'approaching' => $query->slaApproaching(),
                'within' => $query->slaWithin(),
                default => null,
            };
        }

        // 9. Date Range
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}
