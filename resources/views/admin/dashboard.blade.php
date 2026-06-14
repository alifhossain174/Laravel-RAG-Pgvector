@php
    $title = 'Admin Dashboard';
@endphp

@extends('layouts.admin')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Admin Dashboard</h1>
            <p class="mt-2 text-sm text-slate-600">Admin access confirmed for {{ auth()->user()->name }}.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Return to app
        </a>
    </div>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">Phase 1</p>
        <h2 class="mt-2 text-lg font-semibold text-slate-950">Admin foundation is active</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
            This shell confirms the protected admin area is wired through authentication, email verification, and admin authorization.
            User management, document oversight, usage controls, queue views, failed jobs, system health, and settings can be added in later phases.
        </p>
    </section>
@endsection
