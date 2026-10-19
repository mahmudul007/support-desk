<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Sla\SlaStatusResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected SlaStatusResolver $slaStatusResolver
    ) {}

    /**
     * Display the operational dashboard with role-scoped aggregate statistics.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $now = now()->toDateTimeString();

        // Base query scoped by user role
        $baseQuery = Ticket::query()->visibleTo($user);

        // Compute high-efficiency conditional aggregates in a single round-trip
        $kpis = (clone $baseQuery)
            ->selectRaw("
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status NOT IN ('".TicketStatus::Resolved->value."', '".TicketStatus::Closed->value."') THEN 1 ELSE 0 END) as total_open,
                SUM(CASE WHEN assigned_agent_id IS NULL AND status NOT IN ('".TicketStatus::Resolved->value."', '".TicketStatus::Closed->value."') THEN 1 ELSE 0 END) as unassigned,
                SUM(CASE WHEN priority = '".TicketPriority::Critical->value."' AND status NOT IN ('".TicketStatus::Resolved->value."', '".TicketStatus::Closed->value."') THEN 1 ELSE 0 END) as critical_open,
                SUM(CASE WHEN sla_due_at IS NOT NULL AND sla_due_at < '{$now}' AND status NOT IN ('".TicketStatus::Resolved->value."', '".TicketStatus::Closed->value."') THEN 1 ELSE 0 END) as sla_breached,
                SUM(CASE WHEN sla_warning_at IS NOT NULL AND sla_due_at IS NOT NULL AND sla_warning_at <= '{$now}' AND sla_due_at >= '{$now}' AND status NOT IN ('".TicketStatus::Resolved->value."', '".TicketStatus::Closed->value."') THEN 1 ELSE 0 END) as sla_approaching,
                SUM(CASE WHEN status IN ('".TicketStatus::Resolved->value."', '".TicketStatus::Closed->value."') THEN 1 ELSE 0 END) as resolved_total
            ")
            ->first();

        // Priority breakdown for active tickets
        $priorityCounts = (clone $baseQuery)
            ->open()
            ->groupBy('priority')
            ->selectRaw('priority, count(*) as count')
            ->pluck('count', 'priority')
            ->all();

        // Agent workload table (for staff)
        $agentWorkloads = collect();
        if ($user->isAdmin() || $user->isAgent()) {
            $agentWorkloads = User::query()
                ->agents()
                ->withCount([
                    'assignedTickets as open_count' => fn ($q) => $q->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed]),
                    'assignedTickets as breached_count' => fn ($q) => $q->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])->whereNotNull('sla_due_at')->where('sla_due_at', '<', now()),
                ])
                ->orderByDesc('open_count')
                ->get();
        }

        // Recent 5 active tickets
        $recentTickets = (clone $baseQuery)
            ->with(['category:id,name', 'assignedAgent:id,name', 'creator:id,name'])
            ->latest('id')
            ->take(5)
            ->get();

        return view('dashboard', [
            'kpis' => $kpis,
            'priorityCounts' => $priorityCounts,
            'agentWorkloads' => $agentWorkloads,
            'recentTickets' => $recentTickets,
            'slaResolver' => $this->slaStatusResolver,
        ]);
    }
}
