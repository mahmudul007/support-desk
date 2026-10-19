<x-guest-layout>
    <!-- Session Status -->
    @if (session('status'))
        <div class="mb-4 text-sm font-medium text-emerald-600 bg-emerald-50 p-3 rounded-lg border border-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="mt-1 block w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-slate-900 text-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500 @error('email') border-rose-500 @enderror">
            @error('email')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="mt-1 block w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-slate-900 text-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500 @error('password') border-rose-500 @enderror">
            @error('password')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
            <label class="flex items-center">
                <input type="checkbox" name="remember" class="w-4 h-4 text-sky-600 border-slate-300 rounded focus:ring-sky-500">
                <span class="ml-2 text-sm text-slate-600">Remember me</span>
            </label>
        </div>

        <div>
            <button type="submit"
                    class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
                Sign in
            </button>
        </div>

        <div class="text-center text-sm text-slate-500 pt-2 border-t border-slate-100">
            Don't have an account?
            <a href="{{ route('register') }}" class="font-medium text-sky-600 hover:text-sky-500">Register as Customer</a>
        </div>
    </form>

    <!-- Seeded Demo Accounts Quick Reference -->
    <div class="mt-6 pt-4 border-t border-slate-100">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Demo Credentials (Password: password)</p>
        <div class="text-xs text-slate-600 space-y-1 bg-slate-50 p-3 rounded-lg border border-slate-200/60 font-mono">
            <div><span class="text-sky-700 font-semibold">Admin:</span> admin@supportdesk.test</div>
            <div><span class="text-emerald-700 font-semibold">Agent:</span> agent1@supportdesk.test</div>
            <div><span class="text-slate-700 font-semibold">Customer:</span> customer1@supportdesk.test</div>
        </div>
    </div>
</x-guest-layout>

