@php
    $title = 'Admin User Details';
@endphp

@extends('layouts.admin')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">Back to users</a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ $user->name }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $user->email }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($user->is_admin)
                <form method="POST" action="{{ route('admin.users.demote', $user) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Remove admin
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.users.promote', $user) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-lg border border-teal-200 bg-white px-4 py-2.5 text-sm font-semibold text-teal-700 hover:bg-teal-50">
                        Make admin
                    </button>
                </form>
            @endif

            @if ($user->is_suspended)
                <form method="POST" action="{{ route('admin.users.activate', $user) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-emerald-200 hover:bg-emerald-700">
                        Activate
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-rose-200 hover:bg-rose-700">
                        Suspend
                    </button>
                </form>
            @endif
        </div>
    </div>

    <section class="mt-6 grid gap-6 lg:grid-cols-[0.9fr_1.4fr]">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-950">Profile</h2>
            <dl class="mt-5 grid gap-4 text-sm">
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Role</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $user->is_admin ? 'Admin' : 'Normal user' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Account status</dt>
                    <dd class="mt-1 font-semibold {{ $user->is_suspended ? 'text-rose-700' : 'text-emerald-700' }}">{{ $user->is_suspended ? 'Suspended' : 'Active' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Email verification</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $user->email_verified_at ? 'Verified '.$user->email_verified_at->diffForHumans() : 'Unverified' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Created</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $user->created_at->format('M j, Y g:i A') }}</dd>
                </div>
            </dl>
        </div>

        <div>
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($profileMetrics as $metric)
                    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">{{ $metric['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $metric['value'] }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $metric['helper'] }}</p>
                    </article>
                @endforeach
            </section>

            <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-950">Document summary</h2>
                </div>
                <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($documentSummary as $status)
                        <div class="rounded-lg bg-slate-50 p-4">
                            <p class="text-sm text-slate-500">{{ $status['label'] }}</p>
                            <p class="mt-1 text-xl font-semibold text-slate-950">{{ number_format($status['value']) }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </section>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-950">Usage limits</h2>
            <p class="mt-1 text-sm text-slate-500">Blank numeric fields use the default limit. Zero disables that specific limit.</p>
        </div>
        <form method="POST" action="{{ route('admin.users.limits.update', $user) }}" class="p-5">
            @csrf
            @method('PATCH')

            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <label class="mb-5 flex items-center gap-3 rounded-lg bg-slate-50 p-4 text-sm font-medium text-slate-700">
                <input type="hidden" name="is_unlimited" value="0">
                <input type="checkbox" name="is_unlimited" value="1" @checked(old('is_unlimited', $user->limit?->is_unlimited ?? false)) class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                Unlimited account
            </label>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ([
                    'daily_chat_limit' => 'Daily chat limit',
                    'daily_embedding_limit' => 'Daily embedding limit',
                    'monthly_upload_limit' => 'Monthly upload limit',
                    'max_documents' => 'Max documents',
                    'max_storage_mb' => 'Max storage MB',
                    'max_file_size_mb' => 'Max file size MB',
                ] as $field => $label)
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                        <input name="{{ $field }}" type="number" min="0" value="{{ old($field, $user->limit?->{$field}) }}" placeholder="Default: {{ $limitDefaults[$field] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                        <span class="mt-1 block text-xs text-slate-500">Effective: {{ number_format($limitValues[$field]) }}</span>
                    </label>
                @endforeach
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Allowed MIME types</span>
                    <textarea name="allowed_mime_types" rows="4" placeholder="Leave blank to allow app-supported document types" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">{{ old('allowed_mime_types', implode("\n", $user->limit?->allowed_mime_types ?? [])) }}</textarea>
                    <span class="mt-1 block text-xs text-slate-500">One per line or comma-separated. This does not bypass app upload validation.</span>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Notes</span>
                    <textarea name="notes" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">{{ old('notes', $user->limit?->notes) }}</textarea>
                </label>
            </div>

            <div class="mt-5 flex justify-end">
                <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
                    Save limits
                </button>
            </div>
        </form>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Latest documents</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($latestDocuments as $document)
                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-950">{{ $document->displayTitle() }}</p>
                            <p class="truncate text-sm text-slate-500">{{ $document->original_filename }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-medium text-slate-700">{{ $document->statusLabel() }}</p>
                            <p class="text-xs text-slate-500">{{ $document->formattedFileSize() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-500">No documents yet.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Latest conversations</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($latestConversations as $conversation)
                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-950">{{ $conversation->title }}</p>
                            <p class="truncate text-sm text-slate-500">{{ str($conversation->scope)->title() }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-medium text-slate-700">{{ number_format($conversation->messages_count) }} messages</p>
                            <p class="text-xs text-slate-500">{{ $conversation->created_at->format('M j, Y') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-500">No conversations yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
