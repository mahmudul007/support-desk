<x-app-layout>
    <x-slot:title>Dashboard - Support Desk</x-slot:title>

    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                    Operational Dashboard
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Welcome back, {{ auth()->user()->name }} ({{ auth()->user()->role->label() }}). Current SLA monitoring overview:
                </p>
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

        <!-- KPI Metric Cards Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
            <!-- Total Open -->
            <a href="{{ route('tickets.index', ['tab' => 'active']) }}"
               class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:border-sky-300 transition-colors">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Open</div>
                <div class="text-2xl font-bold text-slate-900 mt-2 font-mono">{{ $kpis->total_open ?? 0 }}</div>
            </a>

            <!-- Unassigned Pool -->
            <a href="{{ route('tickets.index', ['tab' => 'active', 'assigned_agent_id' => 'unassigned']) }}"
               class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:border-sky-300 transition-colors">
                <div class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Unassigned</div>
                <div class="text-2xl font-bold text-amber-700 mt-2 font-mono">{{ $kpis->unassigned ?? 0 }}</div>
            </a>

            <!-- Critical Open -->
            <a href="{{ route('tickets.index', ['tab' => 'active', 'priority' => 'critical']) }}"
               class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:border-rose-300 transition-colors">
                <div class="text-xs font-semibold text-rose-600 uppercase tracking-wider">Critical Open</div>
                <div class="text-2xl font-bold text-rose-700 mt-2 font-mono">{{ $kpis->critical_open ?? 0 }}</div>
            </a>

            <!-- Approaching Breach (80%) -->
            <a href="{{ route('tickets.index', ['tab' => 'active', 'sla_status' => 'approaching']) }}"
               class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:border-amber-300 transition-colors">
                <div class="text-xs font-semibold text-amber-600 uppercase tracking-wider">SLA Warning</div>
                <div class="text-2xl font-bold text-amber-700 mt-2 font-mono">{{ $kpis->sla_approaching ?? 0 }}</div>
            </a>

            <!-- Breached SLA -->
            <a href="{{ route('tickets.index', ['tab' => 'active', 'sla_status' => 'breached']) }}"
               class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:border-rose-300 transition-colors">
                <div class="text-xs font-semibold text-rose-600 uppercase tracking-wider">SLA Breached</div>
                <div class="text-2xl font-bold text-rose-700 mt-2 font-mono">{{ $kpis->sla_breached ?? 0 }}</div>
            </a>

            <!-- Resolved Total -->
            <a href="{{ route('tickets.index', ['tab' => 'resolved']) }}"
               class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:border-emerald-300 transition-colors">
                <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Resolved</div>
                <div class="text-2xl font-bold text-emerald-700 mt-2 font-mono">{{ $kpis->resolved_total ?? 0 }}</div>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Active Tickets By Priority -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Open Tickets by Priority</h3>
                <div class="space-y-3">
                    @php
                        $priorities = [
                            'critical' => ['label' => 'Critical (2h)', 'color' => 'bg-rose-500', 'count' => $priorityCounts['critical'] ?? 0],
                            'high' => ['label' => 'High (8h)', 'color' => 'bg-amber-500', 'count' => $priorityCounts['high'] ?? 0],
                            'medium' => ['label' => 'Medium (24h)', 'color' => 'bg-sky-500', 'count' => $priorityCounts['medium'] ?? 0],
                            'low' => ['label' => 'Low (48h)', 'color' => 'bg-slate-400', 'count' => $priorityCounts['low'] ?? 0],
                        ];
                        $maxCount = max(1, ...array_column($priorities, 'count'));
                    @endphp

                    @foreach ($priorities as $key => $data)
                        <div>
                            <div class="flex justify-between text-xs font-medium text-slate-700 mb-1">
                                <span>{{ $data['label'] }}</span>
                                <span class="font-bold font-mono">{{ $data['count'] }}</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div class="{{ $data['color'] }} h-2 rounded-full transition-all"
                                     style="width: {{ ($data['count'] / $maxCount) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Agent Workloads (Staff view) -->
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                        {{ auth()->user()->isCustomer() ? 'Recent Support Activity' : 'Agent Workload & Capacity' }}
                    </h3>
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('imports.index') }}" class="text-xs text-sky-600 hover:underline font-semibold">
                            Bulk Import Tickets →
                        </a>
                    @endif
                </div>

                @if ($agentWorkloads->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs divide-y divide-slate-100 text-left">
                            <thead class="text-slate-400 uppercase font-semibold">
                                <tr>
                                    <th class="py-2">Agent Name</th>
                                    <th class="py-2">Availability</th>
                                    <th class="py-2">Active Workload</th>
                                    <th class="py-2">Capacity Cap</th>
                                    <th class="py-2 text-right">Breached SLAs</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach ($agentWorkloads as $agent)
                                    <tr>
                                        <td class="py-2.5 font-semibold text-slate-900">{{ $agent->name }}</td>
                                        <td class="py-2.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $agent->is_available ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                                {{ $agent->is_available ? 'Available' : 'Busy' }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 font-mono font-bold text-slate-800">{{ $agent->open_count }} tickets</td>
                                        <td class="py-2.5 text-slate-500">{{ $agent->max_open_tickets ? $agent->max_open_tickets.' max' : 'No cap' }}</td>
                                        <td class="py-2.5 text-right font-mono font-bold {{ $agent->breached_count > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                            {{ $agent->breached_count }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-xs text-slate-400 py-4">No active support agents configured.</p>
                @endif
            </div>
        </div>

        <!-- Recent Tickets Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Recently Created Tickets</h3>
                <a href="{{ route('tickets.index') }}" class="text-xs text-sky-600 hover:underline font-semibold">
                    View All Tickets →
                </a>
            </div>

            @if ($recentTickets->isEmpty())
                <p class="text-xs text-slate-400 py-4 text-center">No recent tickets to display.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs text-left divide-y divide-slate-100">
                        <thead class="text-slate-400 uppercase font-semibold">
                            <tr>
                                <th class="py-2">Ticket ID</th>
                                <th class="py-2">Subject</th>
                                <th class="py-2">Priority</th>
                                <th class="py-2">Status</th>
                                <th class="py-2">Assigned Agent</th>
                                <th class="py-2 text-right">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($recentTickets as $ticket)
                                <tr class="hover:bg-slate-50">
                                    <td class="py-2.5 font-mono font-semibold text-sky-700">
                                        <a href="{{ route('tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a>
                                    </td>
                                    <td class="py-2.5 font-medium text-slate-800">{{ $ticket->subject }}</td>
                                    <td class="py-2.5">
                                        <span class="px-2 py-0.5 rounded font-semibold text-[10px] bg-slate-100 text-slate-700">
                                            {{ $ticket->priority->label() }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 text-slate-600">{{ $ticket->status->label() }}</td>
                                    <td class="py-2.5 text-slate-600">{{ $ticket->assignedAgent?->name ?? 'Unassigned' }}</td>
                                    <td class="py-2.5 text-right text-slate-400">{{ $ticket->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

