<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ActivityType;
use App\Events\SlaApproaching;
use App\Events\TicketEscalated;
use App\Models\Ticket;
use App\Services\Activity\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EscalateSlaTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:escalate-sla';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan active tickets for approaching SLA deadlines and auto-escalate breached tickets.';

    public function __construct(
        protected ActivityLogger $activityLogger
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();
        $approachingCount = 0;
        $escalatedCount = 0;

        // 1. Process Approaching SLA Tickets (Warning Window)
        Ticket::query()
            ->open()
            ->whereNotNull('sla_warning_at')
            ->whereNotNull('sla_due_at')
            ->where('sla_warning_at', '<=', $now)
            ->where('sla_due_at', '>', $now)
            ->whereNull('approaching_notified_at')
            ->chunkById(100, function ($tickets) use (&$approachingCount, $now): void {
                foreach ($tickets as $ticket) {
                    $affected = Ticket::query()
                        ->whereKey($ticket->id)
                        ->whereNull('approaching_notified_at')
                        ->update(['approaching_notified_at' => $now]);

                    if ($affected > 0) {
                        $approachingCount++;
                        $this->activityLogger->log(
                            $ticket,
                            ActivityType::SlaWarning,
                            null,
                            new: 'SLA approaching threshold reached (80%)'
                        );
                        event(new SlaApproaching($ticket->fresh()));
                    }
                }
            });

        // 2. Process Breached SLA Tickets (Escalation)
        Ticket::query()
            ->open()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', $now)
            ->whereNull('escalated_at')
            ->chunkById(100, function ($tickets) use (&$escalatedCount, $now): void {
                foreach ($tickets as $ticket) {
                    $affected = Ticket::query()
                        ->whereKey($ticket->id)
                        ->whereNull('escalated_at')
                        ->update([
                            'breached_at' => $now,
                            'escalated_at' => $now,
                            'escalation_count' => DB::raw('escalation_count + 1'),
                        ]);

                    if ($affected > 0) {
                        $escalatedCount++;
                        $this->activityLogger->log(
                            $ticket,
                            ActivityType::Escalated,
                            null,
                            new: 'SLA breached. Escalated to administration.'
                        );
                        event(new TicketEscalated($ticket->fresh()));
                    }
                }
            });

        $this->info("SLA scan complete. Warnings sent: {$approachingCount}. Tickets escalated: {$escalatedCount}.");

        return self::SUCCESS;
    }
}
