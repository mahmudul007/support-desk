<?php

declare(strict_types=1);

namespace App\Services\Sla;

use App\Models\Ticket;
use Carbon\CarbonInterface;

class SlaStatusResolver
{
    /**
     * Resolve status key for a given ticket.
     *
     * @return 'met'|'breached_resolved'|'breached'|'approaching'|'within'|'no_sla'
     */
    public function resolve(Ticket $ticket, ?CarbonInterface $now = null): string
    {
        if (! $ticket->sla_due_at) {
            return 'no_sla';
        }

        $currentTime = $now ?? now();

        // If ticket is resolved or closed, evaluate against resolved_at timestamp
        if (! $ticket->isOpen()) {
            $completionTime = $ticket->resolved_at ?? $ticket->closed_at ?? $currentTime;

            return $completionTime->lessThanOrEqualTo($ticket->sla_due_at) ? 'met' : 'breached_resolved';
        }

        // Active ticket checks
        if ($currentTime->greaterThan($ticket->sla_due_at)) {
            return 'breached';
        }

        if ($ticket->sla_warning_at && $currentTime->greaterThanOrEqualTo($ticket->sla_warning_at)) {
            return 'approaching';
        }

        return 'within';
    }

    /**
     * Get user-friendly badge label.
     */
    public function label(string $status): string
    {
        return match ($status) {
            'met' => 'SLA Met',
            'breached_resolved' => 'Resolved (Breached)',
            'breached' => 'SLA Breached',
            'approaching' => 'Approaching Breach',
            'within' => 'Within SLA',
            default => 'No SLA',
        };
    }

    /**
     * Get Tailwind CSS classes for badge display.
     */
    public function badgeClasses(string $status): string
    {
        return match ($status) {
            'met' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'within' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'approaching' => 'bg-amber-100 text-amber-800 border-amber-200',
            'breached', 'breached_resolved' => 'bg-rose-100 text-rose-800 border-rose-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }
}
