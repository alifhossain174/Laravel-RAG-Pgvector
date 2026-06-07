<x-guest-layout
    title="Confirm password"
    heading="Confirm your password"
    description="This secure area needs one more password check before continuing."
>
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Password</span>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
            @error('password')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </label>

        <button type="submit" class="w-full rounded-lg bg-teal-600 px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
            Confirm password
        </button>
    </form>
</x-guest-layout>
