{{--
    PDPA-01 Privacy Request - one request, its assembled response and its
    correction notes.

    THE ORDER OF THIS SCREEN IS THE ORDER OF THE WORK. Identity first, because
    nothing may be collected before it. Then the assembled response. Then
    release, which is the last point at which a mistake can be caught.

    THE BASIS IS SHOWN BESIDE EVERY ITEM, not just the content. A reviewer
    deciding whether something may be disclosed needs to see WHY it is being
    described rather than disclosed, and the band and treatment say so.
--}}
@extends('layouts.shell')

@section('title', $request->reference.' · '.config('app.name'))
@section('page-title', 'Privacy request '.$request->reference)
@section('page-subtitle', $request->request_type->label().' request from '.$request->subject_name)

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('admin.governance.privacy-requests') }}">Privacy Requests</a>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"/></svg>
            <span aria-current="page">{{ $request->reference }}</span>
        </nav>

        {{-- Where this request stands, and what happens next. --}}
        <section class="card" aria-labelledby="state-heading">
            <div class="card-header">
                <h2 class="card-title" id="state-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-flag"/></svg>
                    Where this stands
                </h2>
                <span class="badge {{ $request->status->badge() }}">{{ $request->status->label() }}</span>
            </div>

            <p class="card-note">{{ $request->status->explanation() }}</p>

            <dl class="detail-grid">
                <div>
                    <dt>Person</dt>
                    <dd>
                        {{ $request->subject_name }}
                        <span class="cell-note">{{ $request->subject_email }}</span>
                        @unless ($request->subjectHasAccount())
                            <span class="cell-note">
                                No SemantIQ account is linked. Records about them may still exist.
                            </span>
                        @endunless
                    </dd>
                </div>
                <div>
                    <dt>Received</dt>
                    <dd>
                        {{ $request->received_at?->format('j M Y') ?? 'Not recorded' }}
                        @if ($request->received_channel)
                            <span class="cell-note">by {{ $request->received_channel }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Identity</dt>
                    <dd>
                        @if ($request->isIdentityVerified())
                            Verified {{ $request->identity_verified_at?->format('j M Y') }}
                            <span class="cell-note">{{ $methods[$request->identity_verification_method] ?? $request->identity_verification_method }}</span>
                        @else
                            <span class="badge badge-warning">Not verified</span>
                            <span class="cell-note">Nothing is collected until this is done.</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Deadline</dt>
                    <dd>
                        @if ($request->due_at)
                            {{ $request->due_at->format('j M Y') }}
                            <span class="badge {{ $request->urgencyBadge() }}">{{ $request->urgency() }}</span>
                        @else
                            <span class="cell-note">Fixed once identity is verified.</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        {{-- Step one. The hard gate. --}}
        @unless ($request->isIdentityVerified())
            <section class="card" aria-labelledby="verify-heading">
                <div class="card-header">
                    <h2 class="card-title" id="verify-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
                        Verify who this person is
                    </h2>
                </div>

                <div class="alert alert-warning" role="alert">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                    <span>
                        <strong>Nothing is collected until this is recorded.</strong>
                        A request is the obvious way to obtain a stranger's personal data: assert you are
                        them and ask. Verification carries the weight a signed-in session would otherwise
                        carry, so record what was actually checked rather than that somebody was satisfied.
                    </span>
                </div>

                @if ($mayManage)
                    <form class="settings-form" method="POST"
                          action="{{ route('admin.governance.privacy-requests.verify', $request->getKey()) }}">
                        @csrf

                        <div class="field">
                            <label class="label" for="method">How was identity established <span class="required">*</span></label>
                            <select class="input" id="method" name="method" required>
                                @foreach ($methods as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label class="label" for="note">What was checked <span class="required">*</span></label>
                            <textarea class="input" id="note" name="note" rows="3" maxlength="2000" required></textarea>
                            <p class="field-note">
                                What you actually saw or confirmed. This is the record somebody relies on if
                                the disclosure is ever questioned.
                            </p>
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-solid btn-primary" type="submit">Record verification</button>
                        </div>
                    </form>
                @else
                    <p class="card-note">You do not hold the permission to verify identity on a request.</p>
                @endif
            </section>
        @endunless

        {{-- Step two. The assembled response. --}}
        @if ($request->isIdentityVerified())
            <section class="card" aria-labelledby="response-heading">
                <div class="card-header">
                    <h2 class="card-title" id="response-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-file-text"/></svg>
                        Assembled response
                    </h2>
                    <span class="badge badge-neutral">{{ $records->count() }}</span>
                </div>

                @if ($records->isEmpty())
                    <div class="empty-state">
                        <svg class="icon icon-lg" aria-hidden="true"><use href="#i-search"/></svg>
                        <p class="empty-title">Nothing collected yet</p>
                        <p class="empty-note">
                            Identity is verified, so collection may run. It reads every record type in scope
                            and records what it found, including the ones it deliberately withheld.
                        </p>
                    </div>
                @else
                    <p class="card-note">
                        Assembled {{ $request->assembled_at?->format('j M Y H:i') }} UTC. Each item shows the
                        basis on which it may be disclosed. Items marked <strong>Described</strong> state a
                        fact without naming anybody else involved, because the underlying record belongs to
                        another person who has not asked for anything.
                    </p>

                    {{--
                        IDENTICAL FINDINGS ARE SHOWN ONCE, with the record types they
                        cover listed beside them.

                        Found in the browser, not by a test. A subject with no SemantIQ
                        account produces the same sentence from every band C collector -
                        twenty-odd rows of "no account is linked, so nothing can be
                        attributed" - and the three rows that actually say something are
                        lost in the middle of them. A reviewer skimming that list would
                        miss the findings, which defeats the point of having a reviewer.

                        THE STORED ROWS ARE STILL ONE PER RECORD TYPE. This groups only
                        what is displayed. The per-table evidence is what makes the
                        coverage claim checkable afterwards, and collapsing the data
                        would destroy it.
                    --}}
                    @php
                        $grouped = $records->groupBy(fn ($record) => $record->summary
                            .'|'.$record->band->value.'|'.$record->treatment->value);
                    @endphp

                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">What is held</th>
                                    <th scope="col">Record type</th>
                                    <th scope="col">Basis</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grouped as $group)
                                    @php($first = $group->first())
                                    <tr>
                                        <td>
                                            {{ $first->summary }}
                                            @if ($group->count() > 1)
                                                <span class="cell-note">
                                                    The same for all {{ $group->count() }} record types listed.
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @foreach ($group as $record)
                                                <span class="cell-reference">{{ $record->source_table }}</span>
                                            @endforeach
                                        </td>
                                        <td>
                                            <span class="badge {{ $first->treatment->badge() }}">
                                                {{ $first->treatment->label() }}
                                            </span>
                                            <span class="cell-note">{{ $first->band->label() }}</span>
                                            @if ($group->contains(fn ($record) => $record->wasWidened()))
                                                <span class="badge badge-warning">Widened by a reviewer</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($mayManage)
                    <form method="POST"
                          action="{{ route('admin.governance.privacy-requests.assemble', $request->getKey()) }}">
                        @csrf
                        <div class="form-actions">
                            <button class="btn btn-outline" type="submit">
                                {{ $records->isEmpty() ? 'Run collection' : 'Re-run collection' }}
                            </button>
                        </div>
                    </form>
                @endif
            </section>

            {{-- What was deliberately not collected, and why. --}}
            <section class="card" aria-labelledby="excluded-heading">
                <div class="card-header">
                    <h2 class="card-title" id="excluded-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-slash"/></svg>
                        Deliberately not collected
                    </h2>
                    <span class="badge badge-neutral">{{ count($exclusions) }}</span>
                </div>

                <p class="card-note">
                    A response is only as honest as its scope. These record types are out of scope by
                    decision rather than by omission, and each reason is written down so it can be defended
                    or challenged.
                </p>

                <dl class="detail-grid">
                    @foreach ($exclusions as $table => $reason)
                        <div>
                            <dt><span class="cell-reference">{{ $table }}</span></dt>
                            <dd>{{ $reason }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        {{-- Correction notes. --}}
        @if ($request->isIdentityVerified())
            <section class="card" aria-labelledby="notes-heading">
                <div class="card-header">
                    <h2 class="card-title" id="notes-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-edit"/></svg>
                        Disputed records
                    </h2>
                    <span class="badge badge-neutral">{{ $notes->count() }}</span>
                </div>

                <div class="alert alert-info" role="note">
                    <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                    <span>
                        <strong>The audit trail is never edited.</strong>
                        Where somebody says an entry is wrong, the remedy is a note recorded permanently
                        beside it, so anyone reading the entry afterwards sees the challenge too. A note
                        cannot be edited or removed once written, including by the person who wrote it.
                    </span>
                </div>

                @if ($notes->isEmpty())
                    <div class="empty-state">
                        <svg class="icon icon-lg" aria-hidden="true"><use href="#i-check-circle"/></svg>
                        <p class="empty-title">No records have been disputed</p>
                    </div>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">What they say is wrong</th>
                                    <th scope="col">Outcome</th>
                                    <th scope="col">Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($notes as $note)
                                    <tr>
                                        <td>
                                            {{ $note->subject_assertion }}
                                            @if ($note->annotatesAnEvent())
                                                <span class="cell-note">
                                                    Annotates audit entry #{{ $note->audit_event_id }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $note->outcome->badge() }}">
                                                {{ $note->outcome->label() }}
                                            </span>
                                            <span class="cell-note">{{ $note->outcome->explanation() }}</span>
                                        </td>
                                        <td>{{ $note->outcome_reason }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($mayManage && $request->isOpen())
                    <form class="settings-form" method="POST"
                          action="{{ route('admin.governance.privacy-requests.note', $request->getKey()) }}">
                        @csrf

                        <div class="field">
                            <label class="label" for="subject_assertion">What they say is wrong <span class="required">*</span></label>
                            <textarea class="input" id="subject_assertion" name="subject_assertion"
                                      rows="3" maxlength="4000" required></textarea>
                            <p class="field-note">In their terms, not yours.</p>
                        </div>

                        <div class="field">
                            <label class="label" for="audit_event_id">Audit entry it disputes</label>
                            <input class="input" id="audit_event_id" name="audit_event_id" type="number" min="1">
                            <p class="field-note">Optional. Leave empty if the dispute is not about a single entry.</p>
                        </div>

                        <div class="field">
                            <label class="label" for="outcome">Outcome <span class="required">*</span></label>
                            <select class="input" id="outcome" name="outcome" required>
                                @foreach ($outcomes as $outcome)
                                    <option value="{{ $outcome->value }}">{{ $outcome->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label class="label" for="outcome_reason">Why <span class="required">*</span></label>
                            <textarea class="input" id="outcome_reason" name="outcome_reason"
                                      rows="3" maxlength="2000" required></textarea>
                            <p class="field-note">
                                Required on every outcome, including a correction. "Corrected" alone does not
                                say what was corrected or on what basis.
                            </p>
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-solid btn-primary" type="submit">Record note</button>
                        </div>
                    </form>
                @endif
            </section>
        @endif

        {{-- Step two and a half. Somebody has to read it. --}}
        @if ($request->isOpen() && $request->isIdentityVerified() && $request->assembled_at && $mayManage)
            <section class="card" aria-labelledby="review-heading">
                <div class="card-header">
                    <h2 class="card-title" id="review-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-eye"/></svg>
                        Review
                    </h2>
                    @if ($request->reviewed_at)
                        <span class="badge badge-success">Reviewed</span>
                    @else
                        <span class="badge badge-warning">Not reviewed</span>
                    @endif
                </div>

                @if ($request->reviewed_at)
                    <p class="card-note">
                        Reviewed {{ $request->reviewed_at->format('j M Y') }}. Whoever authorises the
                        release must be somebody other than the reviewer.
                    </p>
                @else
                    <p class="card-note">
                        A response cannot be released until a person has read it. Recording your review here
                        means you will <strong>not</strong> be able to authorise the release yourself - that
                        is the point of the step.
                    </p>

                    <form method="POST"
                          action="{{ route('admin.governance.privacy-requests.review', $request->getKey()) }}">
                        @csrf
                        <div class="form-actions">
                            <button class="btn btn-outline" type="submit">Record my review</button>
                        </div>
                    </form>
                @endif
            </section>
        @endif

        {{--
            Step three. Release, refuse or close.

            THE SECTION RENDERS EVEN WHEN RELEASE IS BLOCKED, and says why.
            Hiding the control would leave the reader guessing, and the reason
            is usually "somebody else has to do this" - which nobody can infer
            from an absent button.
        --}}
        @if ($request->isOpen() && $request->isIdentityVerified() && $mayRelease)
            <section class="card" aria-labelledby="decide-heading">
                <div class="card-header">
                    <h2 class="card-title" id="decide-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-send"/></svg>
                        Decide
                    </h2>
                </div>

                <p class="card-note">
                    Authorising a disclosure is a different act from assembling or reviewing one. This is the
                    last point at which a mistake can be caught, so it is deliberately not the same person.
                </p>

                @if ($request->decision !== null)
                    {{-- ALREADY ANSWERED. Both forms are withdrawn rather than
                         offered and then refused: a recorded decision, who made
                         it and when are not overwritten, so a control that
                         cannot succeed must not be presented as if it could.
                         The reason is stated rather than the section merely
                         vanishing, per SEC-DEC-087. --}}
                    <div class="alert alert-info" role="status">
                        <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                        <span>
                            <strong>This request has already been answered.</strong>
                            The decision, who made it and when are part of the record and are not
                            overwritten. Raise a new request if something further is needed.
                        </span>
                    </div>
                @elseif ($releaseBlocker)
                    <div class="alert alert-warning" role="alert">
                        <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                        <span>
                            <strong>You cannot release this response.</strong>
                            {{ $releaseBlocker }}
                        </span>
                    </div>
                @else
                <form class="settings-form" method="POST"
                      action="{{ route('admin.governance.privacy-requests.release', $request->getKey()) }}">
                    @csrf
                    <div class="field">
                        <label class="label" for="evidence_reference">How the response was delivered <span class="required">*</span></label>
                        <input class="input" id="evidence_reference" name="evidence_reference"
                               type="text" maxlength="190" required
                               placeholder="Sent by registered post, tracking 123456">
                        <p class="field-note">
                            SemantIQ sends nothing itself. Without this there is no evidence the person ever
                            received an answer.
                        </p>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-solid btn-primary" type="submit">Release the response</button>
                    </div>
                </form>
                @endif

                {{-- Refusal is NOT gated on a second person: it discloses
                     nothing, and the control exists to stop a disclosure
                     happening on one person's say-so. It IS withdrawn once a
                     decision has been recorded, for the reason above. --}}
                @if ($request->decision === null)
                <form class="settings-form" method="POST"
                      action="{{ route('admin.governance.privacy-requests.refuse', $request->getKey()) }}">
                    @csrf
                    <div class="field">
                        <label class="label" for="reason">Or refuse, with the reason <span class="required">*</span></label>
                        <textarea class="input" id="reason" name="reason" rows="3" maxlength="2000"></textarea>
                        <p class="field-note">
                            Refusing is a lawful outcome. Refusing without a stated reason is not defensible
                            to the person or to a regulator.
                        </p>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-outline" type="submit">Refuse the request</button>
                    </div>
                </form>
                @endif
            </section>
        @endif

        @if ($mayRelease && in_array($request->status->value, ['responded', 'refused'], true))
            <section class="card" aria-labelledby="close-heading">
                <div class="card-header">
                    <h2 class="card-title" id="close-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-archive"/></svg>
                        Close
                    </h2>
                </div>
                <p class="card-note">
                    A closed request cannot be reopened. Raise a new one instead, so the record of what was
                    disclosed on a given date stays exactly as it was.
                </p>
                <form method="POST"
                      action="{{ route('admin.governance.privacy-requests.close', $request->getKey()) }}">
                    @csrf
                    <div class="form-actions">
                        <button class="btn btn-outline" type="submit">Close this request</button>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
