<x-guest-layout
    title="Register"
    heading="Create your RAG workspace"
    description="Register to upload PDFs, prepare embeddings, and chat with verified source citations."
>
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Name</span>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
            @error('name')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </label>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Email</span>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
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
            Create account
        </button>

        <p class="text-center text-sm text-slate-600">
            Already registered?
            <a href="{{ route('login') }}" class="font-semibold text-indigo-700 hover:text-indigo-800">Log in</a>
        </p>
    </form>
</x-guest-layout>
