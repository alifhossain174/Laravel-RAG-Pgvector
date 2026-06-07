<x-guest-layout
    title="Reset password"
    heading="Choose a new password"
    description="Use the reset token from your email to secure your DocuMind account."
>
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Email</span>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
            @error('email')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </label>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Password</span>
            <input id="password" type="password" name="password" required autocomplete="new-password" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
            @error('password')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </label>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Confirm password</span>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
            @error('password_confirmation')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </label>

        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700">
            Reset password
        </button>
    </form>
</x-guest-layout>
