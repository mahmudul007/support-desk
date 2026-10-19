<x-app-layout>
    <x-slot:title>Import Run #{{ $import->id }} - Support Desk</x-slot:title>

    <div class="space-y-6 max-w-5xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('imports.index') }}" class="inline-flex items-center text-xs font-semibold text-slate-500 hover:text-slate-800 mb-1">
                    ← Back to Imports
                </a>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                    Import Run #{{ $import->id }}: {{ $import->original_filename }}
                </h1>
            </div>
            @if ($import->failed_rows > 0)
                <a href="{{ route('imports.errors', $import) }}"
                   class="inline-flex items-center px-4 py-2 bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 text-xs font-semibold rounded-lg transition-colors">
                    Download Error CSV ({{ $import->failed_rows }})
                </a>
            @endif
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm">
                <div class="text-xs text-slate-500 font-semibold uppercase">Total Processed</div>
                <div class="text-2xl font-bold text-slate-900 mt-1">{{ $import->processed_rows }}</div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm">
                <div class="text-xs text-emerald-600 font-semibold uppercase">Success Rows</div>
                <div class="text-2xl font-bold text-emerald-700 mt-1">{{ $import->success_rows }}</div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm">
                <div class="text-xs text-rose-600 font-semibold uppercase">Failed Rows</div>
                <div class="text-2xl font-bold text-rose-700 mt-1">{{ $import->failed_rows }}</div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm">
                <div class="text-xs text-amber-600 font-semibold uppercase">Duplicates Skipped</div>
                <div class="text-2xl font-bold text-amber-700 mt-1">{{ $import->duplicate_rows }}</div>
            </div>
        </div>

        <!-- Error Table (if any errors logged) -->
        @if ($import->errors->isNotEmpty())
            <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
                <h3 class="text-base font-bold text-slate-900 mb-3">Validation Error Details</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-xs font-mono">
                        <thead class="text-slate-400 uppercase">
                            <tr>
                                <th class="py-2.5">Row</th>
                                <th class="py-2.5">Raw Data Sample</th>
                                <th class="py-2.5">Validation Errors</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($import->errors as $err)
                                <tr>
                                    <td class="py-2.5 font-bold text-slate-800">{{ $err->row_number }}</td>
                                    <td class="py-2.5 text-slate-600 max-w-xs truncate">{{ json_encode($err->raw_data) }}</td>
                                    <td class="py-2.5 text-rose-600 font-sans font-medium">{{ json_encode($err->errors) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>

