<x-guest-layout
    title="Forgot password"
    heading="Reset your password"
    description="Enter your email and Laravel will send the default password reset notification."
>
    <x-auth-session-status class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Email</span>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
            @error('email')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </label>

        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700">
            Email password reset link
        </button>

        <p class="text-center text-sm text-slate-600">
            Remembered it?
            <a href="{{ route('login') }}" class="font-semibold text-indigo-700 hover:text-indigo-800">Back to login</a>
        </p>
    </form>
</x-guest-layout>
