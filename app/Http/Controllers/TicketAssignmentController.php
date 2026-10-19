<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketAssignmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TicketAssignmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TicketAssignmentService $assignmentService
    ) {}

    /**
     * Support agent claims/accepts an unassigned ticket.
     * Concurrency-safe: handles race conditions with 409 Conflict.
     */
    public function accept(Request $request, Ticket $ticket): Response
    {
        $this->authorize('assign', $ticket);

        $accepted = $this->assignmentService->accept($ticket, $request->user());

        if (! $accepted) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This ticket has already been accepted by another agent or is no longer open.',
                ], Response::HTTP_CONFLICT);
            }

            return redirect()
                ->route('tickets.show', $ticket)
                ->with('error', 'This ticket was just claimed by another support agent.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Ticket accepted successfully.',
                'ticket' => $ticket->fresh(),
            ]);
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'You have accepted this ticket.');
    }

    /**
     * Admin manually assigns or reassigns a ticket to a support agent.
     */
    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(Response::HTTP_FORBIDDEN, 'Only administrators can manually assign tickets.');
        }

        $validated = $request->validate([
            'assigned_agent_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $agent = User::findOrFail($validated['assigned_agent_id']);

        $this->assignmentService->assign($ticket, $agent, $request->user());

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', "Ticket assigned to {$agent->name}.");
    }
}
