<x-guest-layout
    title="Verify email"
    heading="Verify your email address"
    description="Before entering the document assistant, confirm your email using the link Laravel sent to your inbox."
>
    @if (session('status') == 'verification-link-sent')
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            A new verification link has been sent to your email address.
        </div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
        Email verification protects document workspaces before users can access uploads, documents, and chats.
    </div>

    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700 sm:w-auto">
                Resend verification email
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">
                Log out
            </button>
        </form>
    </div>
</x-guest-layout>
