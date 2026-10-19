<x-app-layout>
    <x-slot:title>Support Tickets - Support Desk</x-slot:title>

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Support Tickets</h1>
                <p class="mt-1 text-sm text-slate-500">Manage, track, and resolve support requests within SLA commitments.</p>
            </div>
            <div>
                <a href="{{ route('tickets.create') }}"
                   class="inline-flex items-center px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Ticket
                </a>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-slate-200">
            <nav class="-mb-px flex space-x-6">
                <a href="{{ route('tickets.index', ['tab' => 'active']) }}"
                   class="py-3 px-1 border-b-2 font-medium text-sm {{ $tab === 'active' ? 'border-sky-600 text-sky-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                    Active Tickets
                </a>
                <a href="{{ route('tickets.index', ['tab' => 'resolved']) }}"
                   class="py-3 px-1 border-b-2 font-medium text-sm {{ $tab === 'resolved' ? 'border-sky-600 text-sky-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                    Resolved / Closed
                </a>
            </nav>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm">
            <form method="GET" action="{{ route('tickets.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                <input type="hidden" name="tab" value="{{ $tab }}">

                <!-- Search -->
                <div>
                    <input type="text" name="keyword" value="{{ request('keyword') }}"
                           placeholder="Search keyword / ID..."
                           class="w-full text-xs px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500">
                </div>

                <!-- Priority -->
                <div>
                    <select name="priority" class="w-full text-xs px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500">
                        <option value="">All Priorities</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" {{ request('priority') === $priority->value ? 'selected' : '' }}>
                                {{ $priority->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Category -->
                <div>
                    <select name="category_id" class="w-full text-xs px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500">
                        <option value="">All Categories</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- SLA Status -->
                <div>
                    <select name="sla_status" class="w-full text-xs px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg focus:ring-sky-500 focus:border-sky-500">
                        <option value="">All SLA Statuses</option>
                        <option value="within" {{ request('sla_status') === 'within' ? 'selected' : '' }}>Within SLA</option>
                        <option value="approaching" {{ request('sla_status') === 'approaching' ? 'selected' : '' }}>Approaching Breach</option>
                        <option value="breached" {{ request('sla_status') === 'breached' ? 'selected' : '' }}>SLA Breached</option>
                    </select>
                </div>

                <!-- Actions -->
                <div class="flex items-center space-x-2">
                    <button type="submit" class="flex-1 px-3 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors text-center">
                        Filter
                    </button>
                    <a href="{{ route('tickets.index', ['tab' => $tab]) }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-lg transition-colors">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Tickets Table Card -->
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
            @if ($tickets->isEmpty())
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900">No tickets found</h3>
                    <p class="mt-1 text-sm text-slate-500">There are no {{ $tab === 'resolved' ? 'resolved' : 'active' }} tickets matching your access permissions.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left">
                        <thead class="bg-slate-50/75 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="px-6 py-3.5">Ticket ID</th>
                                <th scope="col" class="px-6 py-3.5">Subject</th>
                                <th scope="col" class="px-6 py-3.5">Priority</th>
                                <th scope="col" class="px-6 py-3.5">Status</th>
                                <th scope="col" class="px-6 py-3.5">Assigned Agent</th>
                                <th scope="col" class="px-6 py-3.5">SLA Target</th>
                                <th scope="col" class="px-6 py-3.5 text-right">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach ($tickets as $ticket)
                                @php
                                    $statusSlug = $slaResolver->resolve($ticket);
                                    $badgeClass = $slaResolver->badgeClasses($statusSlug);
                                    $badgeLabel = $slaResolver->label($statusSlug);

                                    $priorityColors = match($ticket->priority->value) {
                                        'critical' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        'high' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'medium' => 'bg-sky-50 text-sky-700 border-sky-200',
                                        default => 'bg-slate-50 text-slate-600 border-slate-200',
                                    };
                                @endphp
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap font-mono text-xs font-semibold text-sky-700">
                                        <a href="{{ route('tickets.show', $ticket) }}" class="hover:underline">
                                            {{ $ticket->ticket_number }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-slate-900 line-clamp-1">
                                            <a href="{{ route('tickets.show', $ticket) }}" class="hover:text-sky-600">
                                                {{ $ticket->subject }}
                                            </a>
                                        </div>
                                        <div class="text-xs text-slate-400 mt-0.5">
                                            {{ $ticket->category?->name ?? 'General' }} • By {{ $ticket->creator->name }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border {{ $priorityColors }}">
                                            {{ $ticket->priority->label() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                                            {{ $ticket->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-600">
                                        @if ($ticket->assignedAgent)
                                            <span class="font-medium text-slate-800">{{ $ticket->assignedAgent->name }}</span>
                                        @else
                                            <span class="italic text-slate-400">Unassigned</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badgeClass }}">
                                            {{ $badgeLabel }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-xs text-slate-500">
                                        {{ $ticket->created_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($tickets->hasPages())
                    <div class="p-4 border-t border-slate-100">
                        {{ $tickets->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
