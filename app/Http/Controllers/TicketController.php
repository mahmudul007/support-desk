<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Requests\ChangeStatusRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Sla\SlaStatusResolver;
use App\Services\Tickets\TicketFilter;
use App\Services\Tickets\TicketService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TicketService $ticketService,
        protected SlaStatusResolver $slaStatusResolver,
        protected TicketFilter $ticketFilter,
    ) {}

    /**
     * Display a paginated listing of tickets with active/resolved tabs and search filters.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $user = $request->user();
        $tab = $request->string('tab', 'active')->toString();
        $filters = $request->all();

        $query = Ticket::query()
            ->visibleTo($user)
            ->with([
                'category:id,name',
                'assignedAgent:id,name',
                'creator:id,name',
            ])
            ->latest('id');

        if ($tab === 'resolved') {
            $query->resolvedOrClosed();
        } else {
            $query->open();
        }

        $this->ticketFilter->apply($query, $filters);

        $tickets = $query->paginate(15)->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'tab' => $tab,
            'filters' => $filters,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'priorities' => TicketPriority::cases(),
            'slaResolver' => $this->slaStatusResolver,
        ]);
    }

    /**
     * Show the form for creating a new ticket.
     */
    public function create(): View
    {
        $this->authorize('create', Ticket::class);

        $categories = Category::query()->where('is_active', true)->orderBy('name')->get();

        return view('tickets.create', [
            'categories' => $categories,
            'priorities' => TicketPriority::cases(),
        ]);
    }

    /**
     * Store a newly created ticket.
     */
    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $ticket = $this->ticketService->create($request->validated(), $request->user());

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} created successfully.");
    }

    /**
     * Display the specified ticket with conversation thread and audit timeline.
     */
    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $user = $request->user();

        $ticket->load([
            'category',
            'creator',
            'assignedAgent',
            'replies' => fn ($query) => $query->visibleTo($user)->with(['user', 'attachments'])->oldest(),
            'attachments',
            'activities' => fn ($query) => $query->visibleTo($user)->with('user')->oldest('created_at'),
        ]);

        return view('tickets.show', [
            'ticket' => $ticket,
            'slaResolver' => $this->slaStatusResolver,
            'slaStatus' => $this->slaStatusResolver->resolve($ticket),
            'availableAgents' => ($user->isAdmin() || $user->isAgent())
                ? User::query()->agents()->where('is_available', true)->get()
                : collect(),
        ]);
    }

    /**
     * Update the specified ticket.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->ticketService->update($ticket, $request->validated(), $request->user());

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Ticket updated successfully.');
    }

    /**
     * Change ticket status.
     */
    public function changeStatus(ChangeStatusRequest $request, Ticket $ticket): RedirectResponse
    {
        $newStatus = TicketStatus::from($request->string('status')->toString());

        $this->ticketService->changeStatus($ticket, $newStatus, $request->user());

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', "Ticket status changed to {$newStatus->label()}.");
    }
}
