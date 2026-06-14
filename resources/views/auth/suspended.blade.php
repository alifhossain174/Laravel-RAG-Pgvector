<x-guest-layout
    title="Account suspended"
    heading="Account suspended"
    description="Your DocuMind account is currently unable to access the workspace."
>
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800">
        Please contact support or your workspace administrator if you believe this is a mistake.
    </div>

    <div class="mt-6">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                Log out
            </button>
        </form>
    </div>
</x-guest-layout>
