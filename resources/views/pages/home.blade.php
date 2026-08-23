{{--
    Home - the personal intelligence landing page.

    Sections come from MENU_STRUCTURE.md section 4 and the contract in
    PHASE-00-UI-SHELL.md section 3.

    EVERY SECTION IS AN EMPTY STATE, deliberately. Section 3 of that contract
    says Phase 00 uses safe placeholders and must not fake production insights.
    So nothing here shows a number, a trend or a recommendation: no data source
    is connected, and a plausible-looking figure on a landing page is worse than
    no figure, because somebody will believe it.

    Each empty state says what will be here and what has to happen first, which
    is the only genuinely useful thing an empty screen can do.
--}}
@extends('layouts.shell')

@section('title', 'Home · '.config('app.name'))
@section('page-title', $greeting)
@section('page-subtitle', 'Your intelligence home.')

@section('content')
    <div class="home-context" style="margin-bottom: var(--space-4)">
        <span class="badge badge-info">{{ $user->role->label() }}</span>
        @forelse ($domains as $domain)
            <span class="badge">{{ $domain->label() }}</span>
        @empty
            {{-- Stated rather than hidden. Somebody with no domains sees an
                 empty Home and needs to know it is a permissions matter, not a
                 broken page. --}}
            <span class="badge badge-warning">No business domains assigned</span>
        @endforelse
    </div>

    {{-- My KPIs --}}
    <div class="home-grid">
        @foreach (['Revenue', 'Margin', 'Pipeline', 'Attrition'] as $placeholder)
            <div class="card kpi-tile">
                <span class="kpi-label">{{ $placeholder }}</span>
                {{-- An em-dash-free placeholder, never a zero: a zero reads as a
                     measured value of nothing, which is a different claim. --}}
                <span class="kpi-value">Not available</span>
                <span class="kpi-note">No data source connected yet.</span>
            </div>
        @endforeach
    </div>

    <div class="home-columns">
        @foreach ([
            ['What Changed', 'i-activity', 'Material changes since you last looked, ranked by significance.'],
            ['Attention Required', 'i-bell-ring', 'Issues and risks waiting on a decision from you.'],
            ['Risks', 'i-alert-triangle', 'Emerging risks across your authorised domains.'],
            ['Opportunities', 'i-sparkle', 'Positive signals and growth opportunities worth acting on.'],
            ['AI Insights', 'i-brain', 'Plain explanations drawn from governed data.'],
            ['Recommended Actions', 'i-lightbulb', 'Advisory next steps. Recommendations never act on their own.'],
            ['Recent Decisions', 'i-gavel', 'Decisions you or your team recorded, with their evidence.'],
            ['My Alerts', 'i-bell', 'Alerts you subscribed to or were assigned.'],
        ] as [$title, $icon, $note])
            <section class="card panel">
                <div class="panel-head">
                    <span class="panel-title">
                        <svg class="icon" aria-hidden="true"><use href="#{{ $icon }}"/></svg>
                        {{ $title }}
                    </span>
                </div>
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#{{ $icon }}"/></svg>
                    <span class="empty-title">Nothing to show yet</span>
                    <span class="empty-note">{{ $note }}</span>
                </div>
            </section>
        @endforeach
    </div>
@endsection
