<x-app-layout>
    <x-slot:title>{{ $ticket->ticket_number }} - {{ $ticket->subject }}</x-slot:title>

    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Top Breadcrumb & Status Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="{{ route('tickets.index') }}" class="inline-flex items-center text-xs font-semibold text-slate-500 hover:text-slate-800 mb-1.5">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Tickets
                </a>
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 font-mono">{{ $ticket->ticket_number }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $slaResolver->badgeClasses($slaStatus) }}">
                        {{ $slaResolver->label($slaStatus) }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                        {{ $ticket->status->label() }}
                    </span>
                </div>
            </div>

            <!-- Action Controls (Status Transition & Assignment) -->
            <div class="flex items-center flex-wrap gap-2.5">
                @can('changeStatus', $ticket)
                    <form method="POST" action="{{ route('tickets.status', $ticket) }}" class="inline-flex items-center space-x-2">
                        @csrf
                        @method('PATCH')
                        <select name="status" onchange="this.form.submit()"
                                class="text-xs font-semibold py-2 px-3 bg-white border border-slate-300 rounded-lg text-slate-700 shadow-sm focus:ring-sky-500 focus:border-sky-500">
                            <option value="" disabled selected>Change Status...</option>
                            @foreach ($ticket->status->allowedTransitions() as $transition)
                                <option value="{{ $transition->value }}">Mark as {{ $transition->label() }}</option>
                            @endforeach
                        </select>
                    </form>
                @endcan

                @can('assign', $ticket)
                    @if (Route::has('tickets.accept') && $ticket->assigned_agent_id === null)
                        <form method="POST" action="{{ route('tickets.accept', $ticket) }}">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Accept Ticket
                            </button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        <!-- Main Layout Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Subject, Description & Conversation -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Ticket Subject & Body -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 sm:p-7 shadow-sm">
                    <h2 class="text-xl font-bold text-slate-900">{{ $ticket->subject }}</h2>
                    <div class="mt-4 prose prose-slate max-w-none text-slate-700 text-sm whitespace-pre-line leading-relaxed">
                        {{ $ticket->description }}
                    </div>
                </div>

                <!-- Conversation Thread -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm space-y-6">
                    <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">
                        Conversation Thread
                    </h3>

                    @if ($ticket->replies->isEmpty())
                        <div class="text-center py-6 text-sm text-slate-400">
                            No replies yet. Use the response form below to communicate.
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($ticket->replies as $reply)
                                @php
                                    $isInternal = $reply->is_internal;
                                @endphp
                                <div class="rounded-xl p-4 border {{ $isInternal ? 'bg-amber-50/70 border-amber-200' : 'bg-slate-50/70 border-slate-200/70' }}">
                                    <div class="flex items-center justify-between text-xs mb-2">
                                        <div class="flex items-center space-x-2">
                                            <span class="font-bold text-slate-900">{{ $reply->user->name }}</span>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $reply->user->isCustomer() ? 'bg-slate-200 text-slate-700' : 'bg-sky-100 text-sky-700' }}">
                                                {{ $reply->user->role->label() }}
                                            </span>
                                            @if ($isInternal)
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-200 text-amber-900 tracking-wide uppercase">
                                                    🔒 Internal Note (Hidden from Customer)
                                                </span>
                                            @endif
                                        </div>
                                        <span class="text-slate-400">{{ $reply->created_at->format('M d, Y H:i') }}</span>
                                    </div>
                                    <div class="text-sm text-slate-800 whitespace-pre-line">
                                        {{ $reply->body }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <!-- Reply Form placeholder (integrated in TKT-07) -->
                    @if (Route::has('tickets.replies.store'))
                        <div class="pt-4 border-t border-slate-100">
                            <form method="POST" action="{{ route('tickets.replies.store', $ticket) }}" class="space-y-3">
                                @csrf
                                <textarea name="body" rows="3" required placeholder="Type your response here..."
                                          class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-sky-500 focus:border-sky-500"></textarea>

                                @if (!auth()->user()->isCustomer())
                                    <label class="flex items-center text-xs text-amber-900 font-semibold cursor-pointer">
                                        <input type="checkbox" name="is_internal" value="1" class="rounded text-amber-600 focus:ring-amber-500 mr-2">
                                        Post as private internal agent note
                                    </label>
                                @endif

                                <div class="flex justify-end">
                                    <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg shadow-sm">
                                        Send Reply
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right Col: Meta Properties & Audit Timeline -->
            <div class="space-y-6">
                <!-- Meta Info Card -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ticket Properties</h3>

                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between py-1 border-b border-slate-50">
                            <dt class="text-slate-500">Priority</dt>
                            <dd class="font-semibold text-slate-800">{{ $ticket->priority->label() }}</dd>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-50">
                            <dt class="text-slate-500">Category</dt>
                            <dd class="font-medium text-slate-800">{{ $ticket->category?->name ?? 'None' }}</dd>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-50">
                            <dt class="text-slate-500">Customer</dt>
                            <dd class="font-medium text-slate-800">{{ $ticket->creator->name }}</dd>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-50">
                            <dt class="text-slate-500">Assigned Agent</dt>
                            <dd class="font-medium text-slate-800">{{ $ticket->assignedAgent?->name ?? 'Unassigned' }}</dd>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-50">
                            <dt class="text-slate-500">SLA Due Date</dt>
                            <dd class="font-medium font-mono text-xs text-slate-800">
                                {{ $ticket->sla_due_at ? $ticket->sla_due_at->setTimezone(config('sla.timezone'))->format('Y-m-d H:i') : 'N/A' }}
                            </dd>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-50">
                            <dt class="text-slate-500">Submitted At</dt>
                            <dd class="font-medium text-xs text-slate-600">{{ $ticket->created_at->format('M d, Y H:i') }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Audit History Timeline -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Activity History</h3>

                    @if ($ticket->activities->isEmpty())
                        <p class="text-xs text-slate-400">No activity recorded yet.</p>
                    @else
                        <ol class="relative border-l border-slate-200 ml-2 space-y-4 text-xs">
                            @foreach ($ticket->activities as $activity)
                                <li class="ml-4">
                                    <div class="absolute w-2.5 h-2.5 bg-sky-500 rounded-full -left-1.5 border border-white"></div>
                                    <div class="font-semibold text-slate-800">{{ $activity->type->label() }}</div>
                                    <div class="text-[11px] text-slate-500">
                                        {{ $activity->user?->name ?? 'System' }} • {{ $activity->created_at->format('H:i, M d') }}
                                    </div>
                                    @if ($activity->old_value || $activity->new_value)
                                        <div class="text-[11px] text-slate-600 mt-0.5">
                                            @if ($activity->old_value)
                                                <span class="line-through text-slate-400">{{ $activity->old_value }}</span> →
                                            @endif
                                            <span class="font-medium text-slate-700">{{ $activity->new_value }}</span>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

