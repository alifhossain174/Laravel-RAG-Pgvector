<x-guest-layout
    title="Log in"
    heading="Log in to your workspace"
    description="Access your uploaded documents, processing queue, and source-backed chats."
>
    <x-auth-session-status class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Email</span>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
            @error('email')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </label>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Password</span>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
            @error('password')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </label>

        <div class="flex items-center justify-between gap-4">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate-300 text-teal-600 shadow-sm focus:ring-teal-500">
                <span>Remember me</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">
                    Forgot password?
                </a>
            @endif
        </div>

        <button type="submit" class="w-full rounded-lg bg-teal-600 px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
            Log in
        </button>

        <p class="text-center text-sm text-slate-600">
            New to DocuMind?
            <a href="{{ route('register') }}" class="font-semibold text-teal-700 hover:text-teal-800">Create an account</a>
        </p>
    </form>
</x-guest-layout>
