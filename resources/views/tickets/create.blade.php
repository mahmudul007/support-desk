<x-app-layout>
    <x-slot:title>Create Support Ticket - Support Desk</x-slot:title>

    <div class="max-w-3xl mx-auto space-y-6">
        <div>
            <a href="{{ route('tickets.index') }}" class="inline-flex items-center text-xs font-semibold text-slate-500 hover:text-slate-800 mb-2">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Tickets
            </a>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Create New Support Ticket</h1>
            <p class="mt-1 text-sm text-slate-500">Provide details about your inquiry or technical issue. SLA resolution clock will start upon submission.</p>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 sm:p-8 shadow-sm">
            <form method="POST" action="{{ route('tickets.store') }}" class="space-y-6">
                @csrf

                <!-- Subject -->
                <div>
                    <label for="subject" class="block text-sm font-semibold text-slate-700">Subject / Title</label>
                    <input id="subject" type="text" name="subject" value="{{ old('subject') }}" required autofocus
                           placeholder="Brief summary of your issue..."
                           class="mt-1.5 block w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-slate-900 text-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500 @error('subject') border-rose-500 @enderror">
                    @error('subject')
                        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Category -->
                    <div>
                        <label for="category_id" class="block text-sm font-semibold text-slate-700">Category</label>
                        <select id="category_id" name="category_id"
                                class="mt-1.5 block w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-slate-900 text-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                            <option value="">General Support</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Priority -->
                    <div>
                        <label for="priority" class="block text-sm font-semibold text-slate-700">Priority & SLA Window</label>
                        <select id="priority" name="priority" required
                                class="mt-1.5 block w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-slate-900 text-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}" {{ old('priority', 'medium') === $priority->value ? 'selected' : '' }}>
                                    {{ $priority->label() }} ({{ $priority->defaultResolutionMinutes() / 60 }} Business Hours)
                                </option>
                            @endforeach
                        </select>
                        @error('priority')
                            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-semibold text-slate-700">Description</label>
                    <textarea id="description" name="description" rows="5" required
                              placeholder="Describe the steps to reproduce or details of the issue..."
                              class="mt-1.5 block w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-slate-900 text-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500 @error('description') border-rose-500 @enderror">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100">
                    <a href="{{ route('tickets.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-800">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-colors">
                        Submit Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

