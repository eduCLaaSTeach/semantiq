{{--
    Administration landing.

    A setup journey rather than a technical menu, per PHASE-00-UI-SHELL.md
    section 8. The rail already lists every administration screen; repeating
    that list here would tell an administrator nothing about where to start.

    Each step carries its status, whether SemantIQ automates it or Microsoft
    requires a manual action, and what it depends on. Every status reads "Not
    started", because it is.
--}}
@extends('layouts.shell')

@section('title', 'Platform Overview · '.config('app.name'))
@section('page-title', 'Platform Overview')
@section('page-subtitle', 'Set up SemantIQ for your organisation, in order.')

@section('content')
    <div class="card panel" style="margin-bottom: var(--space-3)">
        <div class="empty" style="padding: var(--space-3)">
            <span class="empty-title">Setup has not started</span>
            <span class="empty-note">
                Nothing is connected yet. Work down the steps below; each one checks its
                own result before the next becomes available.
            </span>
        </div>
    </div>

    <div class="card">
        <ol class="journey">
            @foreach ($steps as $index => $step)
                <li class="journey-step">
                    <span class="journey-number" aria-hidden="true">{{ $index + 1 }}</span>
                    <div class="journey-body">
                        <span class="journey-name">{{ $step['name'] }}</span>
                        <span class="domain-desc">{{ $step['detail'] }}</span>
                        <span class="journey-meta">
                            {{-- Automated or guided is the thing an administrator
                                 most needs to know before starting a step: one
                                 costs a click, the other costs a trip to a
                                 Microsoft portal and a tenant admin's time. --}}
                            <span>{{ $step['automated'] ? 'Automated by SemantIQ' : 'Guided, needs a Microsoft admin' }}</span>
                            <span>Requires {{ $step['role'] }}</span>
                        </span>
                    </div>
                    <span class="badge">Not started</span>
                </li>
            @endforeach
        </ol>
    </div>
@endsection
