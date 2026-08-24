{{--
    PDPA-03 Retention.

    THE MOST IMPORTANT THING ON THIS SCREEN IS THE SENTENCE SAYING IT DELETES
    NOTHING, and it is at the top rather than in a footnote.

    A table full of retention periods reads as protection. Reading it that way
    is how an organisation comes to believe it is compliant while nothing at all
    happens to the data. SemantIQ stores the policy; SEC-DEC-038 records that no
    deletion path exists anywhere in gate 4.

    THE LIST IS OF CATEGORIES, NOT POLICIES. A category with no policy is the
    state that matters most - personal data nobody has decided a period for -
    and listing only the policies that exist would hide precisely that.
--}}
@extends('layouts.shell')

@section('title', 'Retention · '.config('app.name'))
@section('page-title', 'Retention')
@section('page-subtitle', 'How long each kind of personal data is kept, on what basis, and what happens at the end.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        @unless ($storageReady)
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                <span>
                    <strong>Retention storage has not been initialised.</strong>
                    {{ $storageBlocker }}
                </span>
            </div>
        @endunless

        {{-- Load-bearing. Not a footnote, not a tooltip. --}}
        <div class="alert alert-info" role="note">
            <svg class="icon" aria-hidden="true"><use href="#i-alert-circle"/></svg>
            <span>
                <strong>SemantIQ does not delete anything as a result of this screen.</strong>
                These are recorded policies, not enforcement. Nothing here runs, sweeps or removes data,
                and an approved policy means a person agreed the period - not that anything acts on it.
                Disposal remains a deliberate, separately approved operation.
            </span>
        </div>

        @if ($storageReady && $withoutAPeriod > 0)
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                <span>
                    <strong>{{ $withoutAPeriod }}</strong> of {{ $rows->count() }}
                    {{ $withoutAPeriod === 1 ? 'category has' : 'categories have' }} no retention period.
                    That is personal data nobody has decided how long to keep.
                </span>
            </div>
        @endif

        @if ($overdueReviews->isNotEmpty())
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-clock"/></svg>
                <span>
                    <strong>{{ $overdueReviews->count() }}</strong>
                    {{ $overdueReviews->count() === 1 ? 'policy is' : 'policies are' }} past their review date.
                </span>
            </div>
        @endif

        <section class="card" aria-labelledby="retention-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="retention-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-refresh"/></svg>
                    Retention by category
                    @if ($storageReady && $rows->isNotEmpty())
                        <span class="badge">{{ $rows->count() }}</span>
                    @endif
                </h2>
            </div>

            @if (! $storageReady)
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-slash"/></svg>
                    <span class="empty-title">Migration required</span>
                    <span class="empty-note">
                        This screen cannot show what retention has been decided, because the table that
                        holds it has not been created yet. It is not empty - it does not exist.
                    </span>
                </div>
            @elseif ($rows->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>
                    <span class="empty-title">No personal data categories are recorded</span>
                    <span class="empty-note">
                        Retention is decided per category, so there is nothing to decide about yet. Open
                        Personal / Sensitive Data first - it writes a starting set from a scan of this
                        application's own schema.
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Retention policy for each category of personal data</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">Category</th>
                                <th scope="col">Kept for</th>
                                <th scope="col">Counted from</th>
                                <th scope="col">Then</th>
                                <th scope="col">State</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php($policy = $row['policy'])
                                <tr>
                                    <th scope="row" class="cell-heading">
                                        <a href="{{ route('admin.governance.retention.edit', $row['category']) }}">{{ $row['category']->name }}</a>
                                        <span class="cell-note">{{ $row['category']->description }}</span>
                                    </th>
                                    <td>
                                        {{-- "Not Configured" rather than a blank cell or an
                                             invented default. A blank reads as an oversight;
                                             a default would be a compliance claim. --}}
                                        @if ($policy && $policy->hasPeriod())
                                            {{ $policy->periodLabel() }}
                                        @else
                                            <span class="cell-empty">Not Configured</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($policy && $policy->start_event)
                                            {{ config('governance.retention_start_events.'.$policy->start_event, $policy->start_event) }}
                                        @else
                                            <span class="cell-empty">Not Configured</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($policy && $policy->disposal_action)
                                            {{ config('governance.retention_disposal_actions.'.$policy->disposal_action, $policy->disposal_action) }}
                                        @else
                                            <span class="cell-empty">Not Configured</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($policy)
                                            <span class="{{ $policy->status->badge() }}">{{ $policy->status->label() }}</span>
                                            @if ($policy->reviewIsOverdue())
                                                <span class="badge badge-warning">Review overdue</span>
                                            @endif
                                        @else
                                            <span class="cell-empty">Nothing recorded</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- The standing warning about the one table that cannot simply be
             swept, kept beside retention because this is where somebody
             planning a disposal will be looking. --}}
        <section class="card" aria-labelledby="audit-warning-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="audit-warning-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-shield-alert"/></svg>
                    Before anybody disposes of audit data
                </h2>
            </div>
            <div class="record-list">
                <p class="field-help">
                    <strong>The audit trail is protected at the database and cannot be swept.</strong>
                    Two triggers on <code>audit_events</code> refuse every UPDATE and every DELETE, which
                    is what makes the trail evidence rather than a log. Model-level rules would not be
                    enough: they do not fire on a mass delete.
                </p>
                <p class="field-help">
                    Applying a retention period to audit data therefore requires a deliberate operation
                    that drops both triggers, deletes, and recreates them - separately approved, recorded,
                    and proved afterwards. It is not something this screen can or should do, and no part
                    of SemantIQ does it today.
                </p>
            </div>
        </section>
    </div>
@endsection
