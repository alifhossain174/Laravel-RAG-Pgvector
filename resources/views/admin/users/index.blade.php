@php
    $title = 'Admin Users';
@endphp

@extends('layouts.admin')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Users</h1>
            <p class="mt-2 text-sm text-slate-600">Search accounts, review access state, and manage admin or suspension status.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-[1fr_220px_auto]">
            <input name="search" type="search" value="{{ $search }}" placeholder="Search by name or email" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
            <select name="filter" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">All users</option>
                @foreach ($filters as $filter => $label)
                    <option value="{{ $filter }}" @selected($selectedFilter === $filter)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
                Apply
            </button>
        </div>
        @if ($errors->any())
            <p class="mt-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</p>
        @endif
    </form>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">User</th>
                    <th class="px-5 py-3">Role</th>
                    <th class="px-5 py-3">Verified</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Documents</th>
                    <th class="px-5 py-3">Conversations</th>
                    <th class="px-5 py-3">Created</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-5 py-4">
                            <a href="{{ route('admin.users.show', $user) }}" class="font-medium text-slate-950 hover:text-teal-700">{{ $user->name }}</a>
                            <p class="mt-1 text-xs text-slate-500">{{ $user->email }}</p>
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_admin ? 'bg-teal-50 text-teal-700 ring-1 ring-teal-200' : 'bg-slate-100 text-slate-700' }}">
                                {{ $user->is_admin ? 'Admin' : 'Normal' }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-slate-600">{{ $user->email_verified_at ? 'Verified' : 'Unverified' }}</td>
                        <td class="px-5 py-4">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_suspended ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-200' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' }}">
                                {{ $user->is_suspended ? 'Suspended' : 'Active' }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-slate-600">{{ number_format($user->documents_count) }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ number_format($user->conversations_count) }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $user->created_at->format('M j, Y') }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($user->is_admin)
                                    <form method="POST" action="{{ route('admin.users.demote', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Remove admin
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.users.promote', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-lg border border-teal-200 px-3 py-2 text-xs font-semibold text-teal-700 hover:bg-teal-50">
                                            Make admin
                                        </button>
                                    </form>
                                @endif

                                @if ($user->is_suspended)
                                    <form method="POST" action="{{ route('admin.users.activate', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-lg border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                                            Activate
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                            Suspend
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-12 text-center">
                            <p class="font-semibold text-slate-950">No users found</p>
                            <p class="mt-2 text-sm text-slate-500">Adjust the search or filter and try again.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $users->links() }}
            </div>
        @endif
    </section>
@endsection
