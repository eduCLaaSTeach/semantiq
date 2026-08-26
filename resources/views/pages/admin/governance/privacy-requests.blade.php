{{--
    PDPA-01 Privacy Requests - the register.

    WHAT AN EMPTY REGISTER MEANS. Nothing has been asked. It does NOT mean the
    obligation is met, and this screen never renders it as a healthy state -
    SEC-DEC-059 applied to privacy: zero requests and a working process look
    identical from here, and only one of them is good news.

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
            <svg class="icon" aria-hidden="true"><use href="#i-info"/></svg>
            <span>
                <strong>SemantIQ does not send the response.</strong>
                A response is assembled and reviewed here, then delivered by a person outside the
                application. Nothing is generated as a file, nothing is emailed, and the register records
                how each response was actually delivered.
            </span>
        </div>

        <section class="card" aria-labelledby="register-heading">
            <div class="card-header">
                <h2 class="card-title" id="register-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-inbox"/></svg>
                    Requests
                </h2>
                @if ($storageReady)
                    <span class="badge badge-neutral">{{ $requests->count() }}</span>
                @endif
            </div>

            @unless ($storageReady)
                <div class="empty-state">
                    <svg class="icon icon-lg" aria-hidden="true"><use href="#i-database"/></svg>
                    <p class="empty-title">Migration required</p>
                    <p class="empty-note">
                        This screen cannot show what requests have been raised, because the tables that hold
                        them have not been created yet. It is not empty - it does not exist.
                    </p>
                </div>
            @else
                @if ($requests->isEmpty())
                    <div class="empty-state">
                        <svg class="icon icon-lg" aria-hidden="true"><use href="#i-inbox"/></svg>
                        <p class="empty-title">No privacy requests have been raised</p>
                        <p class="empty-note">
                            That means nobody has asked. It does not mean the obligation is met - a request
                            can arrive by any route, and it is only recorded here once somebody records it.
                        </p>
                    </div>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">Reference</th>
                                    <th scope="col">Person</th>
                                    <th scope="col">Asking for</th>
                                    <th scope="col">State</th>
                                    <th scope="col">Deadline</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requests as $item)
                                    <tr>
                                        <td>
                                            <a class="cell-link"
                                               href="{{ route('admin.governance.privacy-requests.show', $item->getKey()) }}">
                                                {{ $item->reference }}
                                            </a>
                                        </td>
                                        <td>
                                            <span class="cell-title">{{ $item->subject_name }}</span>
                                            <span class="cell-note">{{ $item->subject_email }}</span>
                                            @unless ($item->subjectHasAccount())
                                                <span class="cell-note">No SemantIQ account linked</span>
                                            @endunless
                                        </td>
                                        <td>
                                            <span class="cell-title">{{ $item->request_type->label() }}</span>
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
                <div class="card-header">
                    <h2 class="card-title" id="record-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
                        Record a request
                    </h2>
                </div>

                <p class="card-note">
                    Recording a request collects nothing. Nothing is gathered about anybody until their
                    identity has been verified, because otherwise anyone could obtain a stranger's data by
                    asserting they are them.
                </p>

                <form class="settings-form" method="POST"
                      action="{{ route('admin.governance.privacy-requests.store') }}">
                    @csrf

                    <div class="field">
                        <label class="label" for="request_type">What are they asking for <span class="required">*</span></label>
                        <select class="input" id="request_type" name="request_type" required>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(old('request_type') === $type->value)>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="subject_name">Their name <span class="required">*</span></label>
                        <input class="input" id="subject_name" name="subject_name" type="text"
                               maxlength="190" required value="{{ old('subject_name') }}">
                        <p class="field-note">
                            Held on the request itself, so it survives even if their account is later deleted.
                        </p>
                    </div>

                    <div class="field">
                        <label class="label" for="subject_email">Their email address <span class="required">*</span></label>
                        <input class="input" id="subject_email" name="subject_email" type="email"
                               maxlength="190" required value="{{ old('subject_email') }}">
                    </div>

                    <div class="field">
                        <label class="label" for="subject_user_id">Their SemantIQ account id</label>
                        <input class="input" id="subject_user_id" name="subject_user_id" type="number"
                               min="1" value="{{ old('subject_user_id') }}">
                        <p class="field-note">
                            Optional. A person with no account may still have personal data recorded about
                            them, and is entitled to ask for it.
                        </p>
                    </div>

                    <div class="field">
                        <label class="label" for="subject_reference">Their reference</label>
                        <input class="input" id="subject_reference" name="subject_reference" type="text"
                               maxlength="190" value="{{ old('subject_reference') }}">
                    </div>

                    <div class="field">
                        <label class="label" for="received_at">When it arrived <span class="required">*</span></label>
                        <input class="input" id="received_at" name="received_at" type="date"
                               required value="{{ old('received_at', now()->toDateString()) }}">
                    </div>

                    <div class="field">
                        <label class="label" for="received_channel">How it arrived</label>
                        <input class="input" id="received_channel" name="received_channel" type="text"
                               maxlength="32" value="{{ old('received_channel') }}"
                               placeholder="email, post, in person">
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-solid btn-primary" type="submit">Record request</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="card" aria-labelledby="coverage-heading">
            <div class="card-header">
                <h2 class="card-title" id="coverage-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-layers"/></svg>
                    What "everything" means here
                </h2>
            </div>

            <p class="card-note">
                When a response says it collected everything held about a person, this is the scope of that
                claim. <strong>{{ $tablesCovered }}</strong> record types are collected and
                <strong>{{ $tablesExcluded }}</strong> are deliberately excluded, each with a written reason
                you can read on any request. A test fails the build if a new record type is added and is
                neither, so the scope cannot go stale without somebody noticing.
            </p>
        </section>
    </div>
@endsection
