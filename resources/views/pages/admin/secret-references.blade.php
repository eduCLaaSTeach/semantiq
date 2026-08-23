{{--
    ADM-012 Secret References.

    Records WHERE a credential is kept. NO VALUE IS SHOWN HERE, because no value
    is stored: there is no column that could hold one, the model refuses a
    credential-shaped string, and the form request refuses one before that.

    A standard-tier table - a row per reference, sorted so what needs attention
    comes first, and the ones nobody gave a date to sit at the bottom where
    their absence is visible rather than hidden among the healthy ones.
--}}
@extends('layouts.shell')

@section('title', 'Secret References · '.config('app.name'))
@section('page-title', 'Secret References')
@section('page-subtitle', 'Where the credentials this deployment depends on are kept, and when they lapse.')

@section('page-action')
    {{-- Absent, not disabled, while the storage does not exist. A control that
         cannot work must not be rendered as one - the same rule the Session
         Policy screen follows for revocation. --}}
    @if ($storageReady)
        <a class="btn btn-solid btn-primary" href="{{ route('admin.security.secrets.create') }}">
            <svg class="icon" aria-hidden="true"><use href="#i-plus-circle"/></svg>
            <span class="btn-label">New reference</span>
        </a>
    @endif
@endsection

@section('content')
    <div class="stack">
        @include('partials.form-status')

        @unless ($storageReady)
            {{-- The deployment window: code is live, the migration has not run.
                 Said outright rather than shown as an empty list, because "no
                 references yet" would tell somebody the store exists and is
                 empty - a different and far more comforting fact than the
                 truth. --}}
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                <span>
                    <strong>Security storage has not been initialised.</strong>
                    {{ $storageBlocker }}
                </span>
            </div>
        @endunless

        <div class="alert alert-info" role="note">
            <svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>
            <span>
                <strong>No credential value is ever stored here.</strong>
                A reference records the name, the provider, a pointer and the dates - enough to know what
                this system depends on and when it lapses, and nothing that could be used to sign in
                anywhere. SemantIQ does not contact any provider, so every date on this page is one
                somebody typed in.
            </span>
        </div>

        @if ($expiringCount > 0 || $rotationDueCount > 0 || $untrackedCount > 0)
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                <span>
                    @if ($expiringCount > 0)
                        <strong>{{ $expiringCount }}</strong>
                        {{ $expiringCount === 1 ? 'reference has' : 'references have' }}
                        expired or expire within {{ $horizonDays }} days.
                    @endif
                    @if ($rotationDueCount > 0)
                        <strong>{{ $rotationDueCount }}</strong>
                        {{ $rotationDueCount === 1 ? 'is' : 'are' }} due for rotation.
                    @endif
                    @if ($untrackedCount > 0)
                        <strong>{{ $untrackedCount }}</strong>
                        {{ $untrackedCount === 1 ? 'has' : 'have' }} no expiry or rotation date, so nothing is tracking
                        {{ $untrackedCount === 1 ? 'it' : 'them' }}.
                    @endif
                </span>
            </div>
        @endif

        <div class="card">
            <div class="panel-head card-head">
                <h2 class="panel-title">
                    <svg class="icon" aria-hidden="true"><use href="#i-key"/></svg>
                    References
                </h2>
            </div>

            @if (! $storageReady)
                {{-- A DIFFERENT empty state from the one below, on purpose.
                     "None recorded yet" and "we cannot tell you what is
                     recorded" are different facts and must not share a
                     screen. --}}
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-slash"/></svg>
                    <span class="empty-title">Migration required</span>
                    <span class="empty-note">
                        This screen cannot show what credentials are being tracked, because the table that
                        holds them has not been created yet. It is not empty - it does not exist. Adding or
                        changing a reference is refused until the outstanding migrations have been run.
                    </span>
                </div>
            @elseif ($references->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-key"/></svg>
                    <span class="empty-title">No secret references yet</span>
                    <span class="empty-note">
                        This deployment certainly depends on credentials - a database password at the very
                        least, and a client secret once Microsoft Entra is set up. Recording them here is
                        what makes a lapse a diary entry rather than an outage.
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Secret references, most urgent first</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-label">Reference</th>
                                <th scope="col">State</th>
                                <th scope="col">Where it is kept</th>
                                <th scope="col">Environment</th>
                                <th scope="col">Expires</th>
                                <th scope="col">Owner</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($references as $reference)
                                <tr>
                                    <th scope="row" class="cell-heading">
                                        <a href="{{ route('admin.security.secrets.edit', $reference) }}">{{ $reference->name }}</a>
                                        <span class="cell-note">{{ $reference->reference_type->label() }}</span>
                                    </th>
                                    <td><span class="{{ $reference->status()->badgeClass() }}">{{ $reference->status()->label() }}</span></td>
                                    <td>
                                        {{ $reference->provider->label() }}
                                        {{-- The POINTER, which is not a secret. It has been
                                             proved not to be credential-shaped twice before
                                             it reached this page. --}}
                                        <span class="cell-note cell-reference">{{ $reference->reference_identifier }}</span>
                                    </td>
                                    <td>{{ $reference->environment }}</td>
                                    <td>{{ $reference->expires_on?->toFormattedDateString() ?? 'Not recorded' }}</td>
                                    <td>{{ $reference->owner?->name ?? 'Nobody assigned' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
@endsection
