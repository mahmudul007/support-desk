<x-app-layout>
    <x-slot:title>CSV Ticket Imports - Support Desk</x-slot:title>

    <div class="space-y-6 max-w-7xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Ticket Data Imports</h1>
                <p class="mt-1 text-sm text-slate-500">Bulk import tickets using CSV files. Designed for memory efficiency and automatic error auditing.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Upload Box -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
                <h2 class="text-base font-bold text-slate-900 mb-2">Upload CSV File</h2>
                <p class="text-xs text-slate-500 mb-4">Required columns: <code class="font-mono text-sky-700">subject, description, priority</code>. Optional: <code class="font-mono text-sky-700">external_ref, category, status</code>.</p>

                <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <input type="file" name="file" accept=".csv,.txt" required
                               class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
                        @error('file')
                            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="w-full flex justify-center py-2.5 px-4 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                        Start Import
                    </button>
                </form>
            </div>

            <!-- Import History -->
            <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
                <h2 class="text-base font-bold text-slate-900 mb-4">Past Import Runs</h2>

                @if ($imports->isEmpty())
                    <p class="text-sm text-slate-400 py-6 text-center">No CSV imports have been run yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                            <thead class="text-slate-400 font-semibold uppercase">
                                <tr>
                                    <th class="py-2.5">File</th>
                                    <th class="py-2.5">Status</th>
                                    <th class="py-2.5">Processed</th>
                                    <th class="py-2.5">Success</th>
                                    <th class="py-2.5">Failed</th>
                                    <th class="py-2.5">Duplicates</th>
                                    <th class="py-2.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach ($imports as $import)
                                    <tr>
                                        <td class="py-3 font-medium text-slate-800">{{ $import->original_filename }}</td>
                                        <td class="py-3">
                                            <span class="px-2 py-0.5 rounded-full font-semibold {{ $import->status->value === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($import->status->value === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-sky-100 text-sky-800') }}">
                                                {{ $import->status->label() }}
                                            </span>
                                        </td>
                                        <td class="py-3">{{ $import->processed_rows }}</td>
                                        <td class="py-3 text-emerald-600 font-semibold">{{ $import->success_rows }}</td>
                                        <td class="py-3 text-rose-600 font-semibold">{{ $import->failed_rows }}</td>
                                        <td class="py-3 text-amber-600">{{ $import->duplicate_rows }}</td>
                                        <td class="py-3 text-right">
                                            <a href="{{ route('imports.show', $import) }}" class="text-sky-600 hover:underline font-semibold">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

