{{--
    ADM-016 Sovereignty Exceptions.

    A recorded, approved, time-bounded departure from the approved sovereignty
    profile.

    THE APPROVED POSITION IS SHOWN FIRST, above the exceptions. An exception
    only means something against the position it departs from, and a reader who
    has to hold that position in their head while reading a list of departures
    from it will get it wrong.

    NOTHING HERE EDITS THE PROFILE. Exceptions sit beside it. A screen that
    folded them in would make the approved position a lie.
--}}
@extends('layouts.shell')

@section('title', 'Sovereignty Exceptions · '.config('app.name'))
@section('page-title', 'Sovereignty Exceptions')
@section('page-subtitle', 'Recorded departures from the approved sovereignty position, each with an end date.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        @unless ($storageReady)
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                <span>
                    <strong>Sovereignty exception storage has not been initialised.</strong>
                    {{ $storageBlocker }}
                </span>
            </div>
        @endunless

        @if ($awaitingCount > 0)
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-hourglass"/></svg>
                <span>
                    <strong>{{ $awaitingCount }}</strong>
                    {{ $awaitingCount === 1 ? 'exception is' : 'exceptions are' }} waiting for a decision.
                    Until somebody decides, {{ $awaitingCount === 1 ? 'it permits' : 'they permit' }} nothing.
                </span>
            </div>
        @endif

        @if ($expiringSoon->isNotEmpty())
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-clock"/></svg>
                <span>
                    <strong>{{ $expiringSoon->count() }}</strong>
                    {{ $expiringSoon->count() === 1 ? 'exception lapses' : 'exceptions lapse' }} within 30 days.
                    When {{ $expiringSoon->count() === 1 ? 'it does' : 'they do' }}, whatever
                    {{ $expiringSoon->count() === 1 ? 'it permits' : 'they permit' }} stops being permitted -
                    check nothing depends on it still being allowed.
                </span>
            </div>
        @endif

        {{-- The position being departed from. --}}
        <section class="card" aria-labelledby="profile-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="profile-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-globe"/></svg>
                    The approved position
                </h2>
                @if ($inForceProfile)
                    <span class="badge badge-success">Version {{ $inForceProfile->version }}</span>
                @else
                    <span class="badge">Not Configured</span>
                @endif
            </div>

            @if ($inForceProfile)
                <div class="record-list">
                    @foreach ($inForceProfile->geographies() as $question => $value)
                        <div class="record-row">
                            <span class="record-label">{{ $question }}</span>
                            <span class="record-value">{{ $geographies[$value] ?? 'Not Configured' }}</span>
                        </div>
                    @endforeach
                    <div class="record-row">
                        <span class="record-label">Exceptions in force right now</span>
                        <span class="record-value">
                            {{-- Stated even when zero, because "no exceptions"
                                 is the reassuring fact and it should be visible
                                 rather than inferred from an empty list. --}}
                            {{ $inForceCount === 0 ? 'None. The position above applies without exception.' : $inForceCount }}
                        </span>
                    </div>
                </div>
            @else
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-slash"/></svg>
                    <span class="empty-title">No sovereignty profile has been approved</span>
                    <span class="empty-note">
                        An exception is a departure from an approved position, so there is nothing to
                        depart from yet. Approve a sovereignty profile first.
                    </span>
                </div>
            @endif
        </section>

        <section class="card" aria-labelledby="list-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="list-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert-octagon"/></svg>
                    Exceptions
                    @if ($storageReady && $exceptions->isNotEmpty())
                        <span class="badge">{{ $exceptions->count() }}</span>
                    @endif
                </h2>
            </div>

            @if (! $storageReady)
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-slash"/></svg>
                    <span class="empty-title">Migration required</span>
                    <span class="empty-note">
                        This screen cannot show what exceptions have been recorded, because the table
                        that holds them has not been created yet. It is not empty - it does not exist.
                    </span>
                </div>
            @elseif ($exceptions->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                    <span class="empty-title">No exceptions have been requested</span>
                    <span class="empty-note">
                        The approved sovereignty position applies without exception. That is the state to
                        be in; an exception is something to record when it becomes unavoidable, not
                        something to have.
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Sovereignty exceptions, newest first</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">Exception</th>
                                <th scope="col">Aspect</th>
                                <th scope="col">State</th>
                                <th scope="col">Requested by</th>
                                <th scope="col">Decided by</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($exceptions as $exception)
                                <tr>
                                    <th scope="row" class="cell-heading">
                                        {{ $exception->title }}
                                        <span class="cell-note">{{ $exception->justification }}</span>
                                    </th>
                                    <td>
                                        {{ $aspects[$exception->aspect] ?? $exception->aspect }}
                                        @if ($exception->requested_geography)
                                            <span class="cell-note">to {{ $geographies[$exception->requested_geography] ?? $exception->requested_geography }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{-- Derived from the status AND the dates, so an
                                             approved-but-lapsed exception cannot read as live. --}}
                                        <span class="{{ $exception->stateBadge() }}">{{ $exception->state() }}</span>
                                    </td>
                                    <td>
                                        {{ $exception->requestedBy?->name ?? 'Unknown' }}
                                        @if ($exception->requested_at)
                                            <span class="cell-note">{{ $exception->requested_at->format('j M Y') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($exception->decidedBy)
                                            {{ $exception->decidedBy->name }}
                                            <span class="cell-note">{{ $exception->decision_note }}</span>
                                        @elseif ($exception->revokedBy)
                                            Revoked by {{ $exception->revokedBy->name }}
                                            <span class="cell-note">{{ $exception->revocation_reason }}</span>
                                        @else
                                            <span class="cell-empty">Not yet decided</span>
                                        @endif
                                    </td>
                                </tr>

                                @if ($mayDecide)
                                    @if (! $exception->status->isDecided())
                                        <tr>
                                            <td colspan="5">
                                                {{-- The decision form sits under the row it decides, so
                                                     nobody approves the wrong one from a modal. --}}
                                                <form method="POST" action="{{ route('admin.governance.exceptions.decide', $exception) }}" class="inline-form">
                                                    @csrf
                                                    <div class="field">
                                                        <label class="field-label" for="note-{{ $exception->id }}">Reason for the decision<span class="field-required" aria-hidden="true">*</span></label>
                                                        <input class="input" type="text" id="note-{{ $exception->id }}" name="note" required>
                                                    </div>
                                                    <button type="submit" name="decision" value="approve" class="btn btn-solid btn-primary">
                                                        <span class="btn-label">Approve</span>
                                                    </button>
                                                    <button type="submit" name="decision" value="reject" class="btn btn-secondary">
                                                        <span class="btn-label">Reject</span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @elseif ($exception->isInForce())
                                        <tr>
                                            <td colspan="5">
                                                <form method="POST" action="{{ route('admin.governance.exceptions.revoke', $exception) }}" class="inline-form">
                                                    @csrf
                                                    <div class="field">
                                                        <label class="field-label" for="revoke-{{ $exception->id }}">Reason for revoking<span class="field-required" aria-hidden="true">*</span></label>
                                                        <input class="input" type="text" id="revoke-{{ $exception->id }}" name="reason" required>
                                                    </div>
                                                    <button type="submit" class="btn btn-secondary">
                                                        <span class="btn-label">Revoke now</span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endif
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($mayRequest)
            @if ($storageReady)
                <section class="card" aria-labelledby="request-heading">
                    <div class="panel-head card-head">
                        <h2 class="panel-title" id="request-heading">
                            <svg class="icon" aria-hidden="true"><use href="#i-file-text"/></svg>
                            Request an exception
                        </h2>
                    </div>

                    <div class="record-list">
                        <p class="field-help">
                            A request permits nothing. Somebody with approval authority - and never you,
                            if you raise it - has to agree before it applies.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.governance.exceptions.store') }}" class="settings-form">
                        @csrf

                        <div class="settings-fields">
                            <div class="field">
                                <label class="field-label" for="title">Title<span class="field-required" aria-hidden="true">*</span></label>
                                <input class="input" type="text" id="title" name="title" required value="{{ old('title') }}"
                                       @error('title') aria-invalid="true" aria-describedby="title-message" @enderror>
                                <p class="field-help">What this exception is, in a line somebody scanning the list will understand.</p>
                                <p class="field-message" id="title-message">@error('title'){{ $message }}@enderror</p>
                            </div>

                            <div class="field">
                                <label class="field-label" for="aspect">What it departs from<span class="field-required" aria-hidden="true">*</span></label>
                                <select class="input" id="aspect" name="aspect" required>
                                    @foreach ($aspects as $value => $label)
                                        <option value="{{ $value }}" @selected(old('aspect') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="field-message" id="aspect-message">@error('aspect'){{ $message }}@enderror</p>
                            </div>

                            <div class="field">
                                <label class="field-label" for="requested_geography">Where the data would go</label>
                                <select class="input" id="requested_geography" name="requested_geography">
                                    <option value="">Not applicable</option>
                                    @foreach ($geographies as $value => $label)
                                        <option value="{{ $value }}" @selected(old('requested_geography') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="field-message" id="requested_geography-message">@error('requested_geography'){{ $message }}@enderror</p>
                            </div>

                            <div class="field">
                                <label class="field-label" for="justification">Justification<span class="field-required" aria-hidden="true">*</span></label>
                                <textarea class="input" id="justification" name="justification" rows="4" required
                                          @error('justification') aria-invalid="true" aria-describedby="justification-message" @enderror>{{ old('justification') }}</textarea>
                                <p class="field-help">Why this is unavoidable, and what was tried instead. This is the whole basis on which data is allowed to leave its geography, and it is what a reviewer weighs.</p>
                                <p class="field-message" id="justification-message">@error('justification'){{ $message }}@enderror</p>
                            </div>

                            <div class="field">
                                <label class="field-label" for="scope_note">Scope</label>
                                <textarea class="input" id="scope_note" name="scope_note" rows="3"
                                          @error('scope_note') aria-invalid="true" aria-describedby="scope_note-message" @enderror>{{ old('scope_note') }}</textarea>
                                <p class="field-help">Which data, and how much of it. An exception scoped to everything is not an exception.</p>
                                <p class="field-message" id="scope_note-message">@error('scope_note'){{ $message }}@enderror</p>
                            </div>

                            <div class="field">
                                <label class="field-label" for="starts_on">Starts on</label>
                                <input class="input" type="date" id="starts_on" name="starts_on" value="{{ old('starts_on') }}"
                                       @error('starts_on') aria-invalid="true" aria-describedby="starts_on-message" @enderror>
                                <p class="field-help">Leave blank to apply from the moment it is approved.</p>
                                <p class="field-message" id="starts_on-message">@error('starts_on'){{ $message }}@enderror</p>
                            </div>

                            <div class="field">
                                <label class="field-label" for="ends_on">Ends on<span class="field-required" aria-hidden="true">*</span></label>
                                <input class="input" type="date" id="ends_on" name="ends_on" required value="{{ old('ends_on') }}"
                                       @error('ends_on') aria-invalid="true" aria-describedby="ends_on-message" @enderror>
                                <p class="field-help">Required. An exception with no end date is a permanent change to the sovereignty position wearing the word "exception". It stops applying on this date by itself, with nothing needing to run.</p>
                                <p class="field-message" id="ends_on-message">@error('ends_on'){{ $message }}@enderror</p>
                            </div>
                        </div>

                        <div class="settings-foot">
                            <button type="submit" class="btn btn-solid btn-primary">
                                <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                                <span class="btn-label">Request exception</span>
                            </button>
                        </div>
                    </form>
                </section>
            @endif
        @endif
    </div>
@endsection
