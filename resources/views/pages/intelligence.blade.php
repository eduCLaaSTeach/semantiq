{{--
    My Intelligence.

    Renders domain cards FROM ENTITLEMENTS, not from a fixed all-domain menu -
    PHASE-00-UI-SHELL.md section 4 is explicit about that. Somebody entitled to
    Sales alone sees one card, and the others are absent rather than locked.
--}}
@extends('layouts.shell')

@section('title', 'My Intelligence · '.config('app.name'))
@section('page-title', 'My Intelligence')
@section('page-subtitle', 'The business domains you are entitled to.')

@section('content')
    @if ($domains === [])
        <div class="card panel">
            <div class="empty">
                <svg class="icon" aria-hidden="true"><use href="#i-brain"/></svg>
                <span class="empty-title">No domains assigned</span>
                <span class="empty-note">
                    Business domain access is granted separately from your platform role.
                    Ask an administrator to assign the domains you need.
                </span>
            </div>
        </div>
    @else
        <div class="home-grid">
            @foreach ($domains as $domain)
                <article class="card domain-card">
                    <div class="domain-head">
                        <svg class="icon" aria-hidden="true"><use href="#{{ $icons[$domain->value] }}"/></svg>
                        <div>
                            <div class="domain-name">{{ $domain->label() }}</div>
                            <div class="domain-desc">{{ $domain->description() }}</div>
                        </div>
                    </div>
                    <div class="domain-foot">
                        <span class="badge">Not configured</span>
                        @if ($domain->isSensitive())
                            {{-- Named on the domain, not left to each screen to
                                 remember which ones carry restricted fields. --}}
                            <span class="badge badge-violet">Restricted fields</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
