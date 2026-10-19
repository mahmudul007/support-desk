<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreReplyRequest;
use App\Models\Ticket;
use App\Services\Tickets\TicketConversationService;
use Illuminate\Http\RedirectResponse;

class TicketReplyController extends Controller
{
    public function __construct(
        protected TicketConversationService $conversationService
    ) {}

    /**
     * Store a reply or internal note on a ticket.
     */
    public function store(StoreReplyRequest $request, Ticket $ticket): RedirectResponse
    {
        $files = $request->file('attachments', []);

        $this->conversationService->addReply(
            $ticket,
            $request->validated(),
            $request->user(),
            is_array($files) ? $files : [$files]
        );

        $message = ! empty($request->input('is_internal'))
            ? 'Internal note added.'
            : 'Reply submitted successfully.';

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', $message);
    }
}
