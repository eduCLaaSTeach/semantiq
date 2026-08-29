{{--
    PDPA-01 Privacy Requests - the register.

    WHAT AN EMPTY REGISTER MEANS. Nothing has been asked. It does NOT mean the
    obligation is met, and this screen never renders it as a healthy state -
    SEC-DEC-059 applied to privacy: zero requests and a working process look
    identical from here, and only one of them is good news.

    CLASSES ARE THE SHARED ONES, NOT PAGE-SPECIFIC INVENTIONS. An earlier
    version of this file used `card-header`, `card-title`, `empty-state`,
    `card-note`, `table-wrap`, `table`, `label`, `field-note`, `form-actions`
    and `required`. NONE of those is defined in the stylesheet, so nearly half
    this page rendered as unstyled markup: `.card` carries no padding of its
    own, so text sat against the border, and `.empty` - which supplies the
    centring - was never applied. Use the vocabulary the other governance
    screens use, and check it against `resources/css/app.css` before inventing
    a name.

    THE COVERAGE FIGURE IS SHOWN because the honest question a reader has is
    "when you say you collected everything, everything out of what?". The
    numbers come from the collector catalogue and the exclusion register, so
    they cannot drift from what the code actually does.
--}}
@extends('layouts.shell')

@section('title', 'Privacy Requests · '.config('app.name'))
@section('page-title', 'Privacy Requests')
@section('page-subtitle', 'Requests from people about the personal data held about them.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        @unless ($storageReady)
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                <span>
                    <strong>Privacy request storage has not been initialised.</strong>
                    {{ $storageBlocker }}
                </span>
            </div>
        @endunless

        <div class="alert alert-info" role="note">
            <svg class="icon" aria-hidden="true"><use href="#i-alert-circle"/></svg>
            <span>
                <strong>SemantIQ does not send the response.</strong>
                A response is assembled and reviewed here, then delivered by a person outside the
                application. Nothing is generated as a file, nothing is emailed, and the register records
                how each response was actually delivered.
            </span>
        </div>

        <section class="card" aria-labelledby="register-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="register-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-inbox"/></svg>
                    Requests
                    @if ($storageReady)
                        <span class="badge">{{ $requests->count() }}</span>
                    @endif
                </h2>
            </div>

            @unless ($storageReady)
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-database"/></svg>
                    <span class="empty-title">Migration required</span>
                    <span class="empty-note">
                        This screen cannot show what requests have been raised, because the tables that hold
                        them have not been created yet. It is not empty - it does not exist.
                    </span>
                </div>
            @else
                @if ($requests->isEmpty())
                    <div class="empty">
                        <svg class="icon" aria-hidden="true"><use href="#i-inbox"/></svg>
                        <span class="empty-title">No privacy requests have been raised</span>
                        <span class="empty-note">
                            That means nobody has asked. It does not mean the obligation is met - a request
                            can arrive by any route, and it is only recorded here once somebody records it.
                        </span>
                    </div>
                @else
                    <div class="table-scroll">
                        <table class="data-table">
                            <caption class="visually-hidden">Privacy requests, soonest deadline first</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="col-primary">Reference</th>
                                    <th scope="col">Person</th>
                                    <th scope="col">Asking for</th>
                                    <th scope="col">State</th>
                                    <th scope="col">Deadline</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requests as $item)
                                    <tr>
                                        <th scope="row" class="cell-heading">
                                            <a href="{{ route('admin.governance.privacy-requests.show', $item->getKey()) }}">{{ $item->reference }}</a>
                                        </th>
                                        <td>
                                            <span class="cell-heading">{{ $item->subject_name }}</span>
                                            <span class="cell-note">{{ $item->subject_email }}</span>
                                            @unless ($item->subjectHasAccount())
                                                <span class="cell-note">No SemantIQ account linked</span>
                                            @endunless
                                        </td>
                                        <td>
                                            <span class="cell-heading">{{ $item->request_type->label() }}</span>
                                            <span class="cell-note">{{ $item->request_type->explanation() }}</span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $item->status->badge() }}">
                                                {{ $item->status->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $item->urgencyBadge() }}">
                                                {{ $item->urgency() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </section>

        @if ($storageReady && $mayManage)
            <section class="card" aria-labelledby="record-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="record-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-plus-circle"/></svg>
                        Record a request
                    </h2>
                </div>

                <form class="settings-form" method="POST"
                      action="{{ route('admin.governance.privacy-requests.store') }}">
                    @csrf

                    {{-- Load-bearing, so it sits above the first field rather
                         than under the heading where a reader skips it. --}}
                    <p class="field-help">
                        Recording a request collects nothing. Nothing is gathered about anybody until their
                        identity has been verified, because otherwise anyone could obtain a stranger's data
                        by asserting they are them.
                    </p>

                    {{-- `settings-fields` is what constrains the form to a
                         readable 560px. Without it the inputs run the full
                         width of the page and read as a spreadsheet. --}}
                    <div class="settings-fields">
                        <div class="field">
                        <label class="field-label" for="request_type">What are they asking for <span class="field-required" aria-hidden="true">*</span></label>
                        <select class="input" id="request_type" name="request_type" required>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(old('request_type') === $type->value)>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                        <div class="field">
                            <label class="field-label" for="subject_name">Their name <span class="field-required" aria-hidden="true">*</span></label>
                            <input class="input" id="subject_name" name="subject_name" type="text"
                                   maxlength="190" required value="{{ old('subject_name') }}">
                            <p class="field-help">
                                Held on the request itself, so it survives even if their account is later deleted.
                            </p>
                        </div>

                        <div class="field">
                            <label class="field-label" for="subject_email">Their email address <span class="field-required" aria-hidden="true">*</span></label>
                            <input class="input" id="subject_email" name="subject_email" type="email"
                                   maxlength="190" required value="{{ old('subject_email') }}">
                        </div>

                        <div class="field">
                            <label class="field-label" for="subject_user_id">Their SemantIQ account id</label>
                            <input class="input" id="subject_user_id" name="subject_user_id" type="number"
                                   min="1" value="{{ old('subject_user_id') }}">
                            <p class="field-help">
                                Optional. A person with no account may still have personal data recorded about
                                them, and is entitled to ask for it.
                            </p>
                        </div>

                        <div class="field">
                            <label class="field-label" for="subject_reference">Their reference</label>
                            <input class="input" id="subject_reference" name="subject_reference" type="text"
                                   maxlength="190" value="{{ old('subject_reference') }}">
                        </div>

                        <div class="field">
                            <label class="field-label" for="received_at">When it arrived <span class="field-required" aria-hidden="true">*</span></label>
                            <input class="input" id="received_at" name="received_at" type="date"
                                   required value="{{ old('received_at', now()->toDateString()) }}">
                        </div>

                        <div class="field">
                            <label class="field-label" for="received_channel">How it arrived</label>
                            <input class="input" id="received_channel" name="received_channel" type="text"
                                   maxlength="32" value="{{ old('received_channel') }}"
                                   placeholder="email, post, in person">
                        </div>

                    </div>

                    <div class="settings-foot">
                        <button class="btn btn-solid btn-primary" type="submit">Record request</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="card" aria-labelledby="coverage-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="coverage-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-layers"/></svg>
                    What "everything" means here
                </h2>
            </div>

            {{-- The two figures are facts, so they are rendered as facts in
                 the shared record pattern rather than buried in a sentence. --}}
            <div class="record-list">
                <div class="record-row">
                    <span class="record-label">Record types collected</span>
                    <span class="record-value">{{ $tablesCovered }}</span>
                </div>
                <div class="record-row">
                    <span class="record-label">Deliberately excluded</span>
                    <span class="record-value">{{ $tablesExcluded }}</span>
                </div>
                <p class="field-help">
                    When a response says it collected everything held about a person, this is the scope of
                    that claim. Each exclusion carries a written reason you can read on any request, and a
                    test fails the build if a new record type is added and is neither collected nor
                    excluded - so the scope cannot go stale without somebody noticing.
                </p>
            </div>
        </section>
    </div>
@endsection
