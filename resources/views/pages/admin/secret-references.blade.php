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
    <a class="btn btn-solid btn-primary" href="{{ route('admin.security.secrets.create') }}">
        <svg class="icon" aria-hidden="true"><use href="#i-plus-circle"/></svg>
        <span class="btn-label">New reference</span>
    </a>
@endsection

@section('content')
    <div class="stack">
        @include('partials.form-status')

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

            @if ($references->isEmpty())
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
                                <th scope="col" class="col-primary">Reference</th>
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
