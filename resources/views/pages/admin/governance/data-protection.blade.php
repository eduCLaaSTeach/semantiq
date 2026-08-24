{{--
    ADM-014 Data Protection Profile.

    THE SCREEN SHOWS TWO VERSIONS AT ONCE and the separation is the point: the
    version IN FORCE, which is what the organisation has actually decided, and
    the DRAFT, which is what somebody is writing. Collapsing them into one
    "current profile" would make a half-finished edit look like a position that
    had been taken.

    NO FALSE HEALTHY STATES anywhere on this page. SEC-DEC-059 and SEC-DEC-060.
    Nothing approved reads Not Configured, never Healthy. A profile with blank
    compliance-owned fields is INCOMPLETE and says which fields, because a
    screen that called it complete would be claiming compliance nobody made.
--}}
@extends('layouts.shell')

@section('title', 'Data Protection Profile · '.config('app.name'))
@section('page-title', 'Data Protection Profile')
@section('page-subtitle', 'Which privacy regime applies to this organisation, who is accountable, and how quickly a breach must be reported.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        @unless ($storageReady)
            {{-- The deployment window: code is live, the migration has not run.
                 Said outright, because an empty profile form would read as
                 "nothing has been decided" rather than "we cannot tell you". --}}
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                <span>
                    <strong>Data protection storage has not been initialised.</strong>
                    {{ $storageBlocker }}
                </span>
            </div>
        @endunless

        {{-- What is actually in force. The first thing on the page, because it
             is the only thing here that binds anything. --}}
        <section class="card" aria-labelledby="in-force-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="in-force-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
                    Version in force
                </h2>
                @if ($inForce)
                    <span class="badge badge-success">Version {{ $inForce->version }}, approved</span>
                @else
                    <span class="badge">Not Configured</span>
                @endif
            </div>

            @if ($inForce)
                <div class="record-list">
                    <div class="record-row">
                        <span class="record-label">Applicable regime</span>
                        <span class="record-value">{{ $inForce->applicable_regime ?: 'Not Configured' }}</span>
                    </div>
                    <div class="record-row">
                        <span class="record-label">Basis for that determination</span>
                        <span class="record-value">{{ $inForce->regime_basis ?: 'Not Configured' }}</span>
                    </div>
                    <div class="record-row">
                        <span class="record-label">Privacy officer designated</span>
                        <span class="record-value">{{ $inForce->privacy_officer_designated ? 'Yes' : 'No' }}</span>
                    </div>
                    <div class="record-row">
                        <span class="record-label">Breach notification deadline</span>
                        <span class="record-value">
                            @if ($inForce->breach_notification_due_days)
                                {{ $inForce->breach_notification_due_days }} {{ $deadlineUnit }}
                            @else
                                Not Configured
                            @endif
                        </span>
                    </div>
                    <div class="record-row">
                        <span class="record-label">Basis for that deadline</span>
                        <span class="record-value">{{ $inForce->breach_notification_basis ?: 'Not Configured' }}</span>
                    </div>
                    <div class="record-row">
                        <span class="record-label">Approved</span>
                        <span class="record-value">
                            {{ optional($inForce->approved_at)->format('j M Y') ?? 'Unknown' }}
                            @if ($inForce->approvedBy)
                                by {{ $inForce->approvedBy->name }}
                            @endif
                        </span>
                    </div>
                </div>
            @else
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-slash"/></svg>
                    <span class="empty-title">No profile has been approved</span>
                    <span class="empty-note">
                        {{-- Deliberately not "everything is fine by default".
                             There is no default privacy position: either
                             somebody decided one or nobody did. --}}
                        This organisation has not recorded a data protection position. That is not the
                        same as having no obligations - the Singapore PDPA was determined to apply on
                        25 August 2026 - it means nothing has been written down and approved yet.
                        @if ($storageReady)
                            Fill in the draft below and approve it.
                        @endif
                    </span>
                </div>
            @endif
        </section>

        {{-- What is still missing. Named, so the reader can fix it, rather than
             shown as an unexplained warning badge. --}}
        @if ($gaps !== [])
            <section class="card" aria-labelledby="gaps-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="gaps-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-alert-octagon"/></svg>
                        Still to answer
                        <span class="badge badge-warning">{{ count($gaps) }}</span>
                    </h2>
                </div>
                <ul class="field-help">
                    @foreach ($gaps as $gap)
                        <li>{{ $gap }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($storageReady)
            <section class="card" aria-labelledby="draft-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="draft-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-file-text"/></svg>
                        Draft
                    </h2>
                    @if ($draft)
                        <span class="badge badge-warning">Version {{ $draft->version }}, draft</span>
                    @endif
                </div>

                @if ($draft)
                    <p class="field-help">{{ $draft->status->explanation() }}</p>
                @elseif ($inForce)
                    {{-- No draft, so the form is pre-filled from the version in
                         force. Saying so matters: a form that looks editable
                         over an approved profile needs to explain that editing
                         it starts a new version rather than changing this one. --}}
                    <p class="field-help">
                        There is no draft open, so this form shows version {{ $inForce->version }} - the
                        version in force. Saving it starts version {{ $inForce->version + 1 }} as a draft
                        and leaves version {{ $inForce->version }} in force until the new one is approved.
                    </p>
                @else
                    <p class="field-help">
                        There is no draft open. Saving the form below starts one. A draft binds nothing
                        until it is approved.
                    </p>
                @endif

                <form method="POST" action="{{ route('admin.governance.data-protection.update') }}" class="settings-form">
                    @csrf
                    @method('PUT')

                    {{-- One `settings-fields` around the whole form, not one
                         around part of it. The class caps field width, so a
                         field left outside it renders at the card's full width
                         and the form reads as two different forms stacked. --}}
                    <div class="settings-fields">
                        <div class="field">
                            <label class="field-label" for="applicable_regime">Applicable privacy regime</label>
                            <select class="input" id="applicable_regime" name="applicable_regime">
                                <option value="">Not determined</option>
                                @foreach ($regimes as $value => $label)
                                    <option value="{{ $value }}"
                                        @selected(old('applicable_regime', $editing?->applicable_regime) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="field-help">Which law governs the personal data this organisation holds. Where the data sits is a fact; which law governs it is a legal determination.</p>
                            <p class="field-message" id="applicable_regime-message">@error('applicable_regime'){{ $message }}@enderror</p>
                        </div>

                        <div class="field">
                            <label class="field-label" for="breach_notification_due_days">Breach notification deadline, in {{ $deadlineUnit }}</label>
                            <input class="input" type="number" min="1" max="90"
                                   id="breach_notification_due_days" name="breach_notification_due_days"
                                   value="{{ old('breach_notification_due_days', $editing?->breach_notification_due_days ?? $defaultDueDays) }}"
                                   @error('breach_notification_due_days') aria-invalid="true" aria-describedby="breach_notification_due_days-message" @enderror>
                            <p class="field-help">How long there is to notify the regulator once a notifiable breach is identified. SemantIQ ships {{ $defaultDueDays }} as a starting point; confirm the figure your regulator actually requires.</p>
                            <p class="field-message" id="breach_notification_due_days-message">@error('breach_notification_due_days'){{ $message }}@enderror</p>
                        </div>

                    <div class="field">
                        <label class="field-label" for="regime_basis">Basis for the regime determination</label>
                        <textarea class="input" id="regime_basis" name="regime_basis" rows="3"
                                  @error('regime_basis') aria-invalid="true" aria-describedby="regime_basis-message" @enderror>{{ old('regime_basis', $editing?->regime_basis) }}</textarea>
                        {{-- Load-bearing help text. This field is empty on
                             purpose and SemantIQ will never fill it in. --}}
                        <p class="field-help">Why that regime applies - who determined it, when, and on what grounds. SemantIQ deliberately ships this blank: it is a legal judgement, and a plausible sentence generated here would be a compliance claim nobody made.</p>
                        <p class="field-message" id="regime_basis-message">@error('regime_basis'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="breach_notification_basis">Basis or reference for the deadline</label>
                        <textarea class="input" id="breach_notification_basis" name="breach_notification_basis" rows="3"
                                  @error('breach_notification_basis') aria-invalid="true" aria-describedby="breach_notification_basis-message" @enderror>{{ old('breach_notification_basis', $editing?->breach_notification_basis) }}</textarea>
                        <p class="field-help">The provision or guidance the deadline comes from. Also blank on purpose, and for the same reason.</p>
                        <p class="field-message" id="breach_notification_basis-message">@error('breach_notification_basis'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="checkbox">
                            <input type="checkbox" name="privacy_officer_designated" value="1"
                                   @checked(old('privacy_officer_designated', $editing?->privacy_officer_designated))>
                            <span>A privacy officer has been designated</span>
                        </label>
                        <p class="field-help">Tick only when somebody has actually been appointed. A name in the organisation profile is not the same fact as an appointment.</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="notes">Notes</label>
                        <textarea class="input" id="notes" name="notes" rows="3"
                                  @error('notes') aria-invalid="true" aria-describedby="notes-message" @enderror>{{ old('notes', $editing?->notes) }}</textarea>
                        <p class="field-message" id="notes-message">@error('notes'){{ $message }}@enderror</p>
                    </div>
                    </div>

                    <div class="settings-foot">
                        <button type="submit" class="btn btn-solid btn-primary">
                            <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                            <span class="btn-label">Save draft</span>
                        </button>
                    </div>
                </form>
            </section>

            @if ($draft)
                <section class="card" aria-labelledby="approve-heading">
                    <div class="panel-head card-head">
                        <h2 class="panel-title" id="approve-heading">
                            <svg class="icon" aria-hidden="true"><use href="#i-badge-check"/></svg>
                            Approve version {{ $draft->version }}
                        </h2>
                    </div>

                    <p class="field-help">
                        Approving makes this version the one in force and freezes it. Changing it later
                        creates a new version and supersedes this one, so what was in force on any given
                        date stays readable.
                        @if ($gaps !== [])
                            <strong>{{ count($gaps) }}</strong>
                            {{ count($gaps) === 1 ? 'question is' : 'questions are' }} still unanswered above.
                            Approving an incomplete profile is allowed - a partial position recorded honestly
                            is better than none - but the gaps stay visible until they are filled.
                        @endif
                    </p>

                    <form method="POST" action="{{ route('admin.governance.data-protection.approve') }}" class="settings-form">
                        @csrf

                        <div class="settings-fields">
                        <div class="field">
                            <label class="field-label" for="reason">Reason<span class="field-required" aria-hidden="true">*</span></label>
                            <input class="input" type="text" id="reason" name="reason" required
                                   value="{{ old('reason') }}"
                                   @error('reason') aria-invalid="true" aria-describedby="reason-message" @enderror>
                            <p class="field-help">Why this version is being approved. It goes into the audit trail and is what somebody reviewing this decision later will read.</p>
                            <p class="field-message" id="reason-message">@error('reason'){{ $message }}@enderror</p>
                        </div>
                        </div>

                        <div class="settings-foot">
                            <button type="submit" class="btn btn-solid btn-primary">
                                <svg class="icon" aria-hidden="true"><use href="#i-badge-check"/></svg>
                                <span class="btn-label">Approve version {{ $draft->version }}</span>
                            </button>
                        </div>
                    </form>
                </section>
            @endif
        @endif

        @if ($history->isNotEmpty())
            <section class="card" aria-labelledby="history-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="history-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-list-check"/></svg>
                        Version history
                    </h2>
                </div>
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Every version of the data protection profile, newest first</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-label">Version</th>
                                <th scope="col">State</th>
                                <th scope="col">Regime</th>
                                <th scope="col">Approved</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $version)
                                <tr>
                                    <th scope="row" class="cell-heading">{{ $version->version }}</th>
                                    <td><span class="{{ $version->status->badge() }}">{{ $version->status->label() }}</span></td>
                                    <td>{{ $version->applicable_regime ?: 'Not Configured' }}</td>
                                    <td>
                                        @if ($version->approved_at)
                                            {{ $version->approved_at->format('j M Y') }}
                                            @if ($version->approvedBy)
                                                by {{ $version->approvedBy->name }}
                                            @endif
                                        @else
                                            Never
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
