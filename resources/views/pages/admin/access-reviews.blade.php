{{--
    ADM-008 Access Reviews. MENU_STRUCTURE 12.2.

    Create -> open -> decide -> complete -> apply.

    "Decided but never applied" is its own state on this list, because a review
    whose revocations were never carried out is a finding rather than a finished
    review, and folding it into "completed" would hide exactly that.
--}}
@extends('layouts.shell')

@section('title', 'Access Reviews · '.config('app.name'))
@section('page-title', 'Access Reviews')
@section('page-subtitle', 'Periodic checks that people still need the access they have.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        {{-- Why this screen exists, said on the screen.

             Access accumulates. Somebody moves from Finance to Operations and
             keeps Finance; a contractor finishes and keeps their role; a
             temporary grant made during an incident is never taken back. Nobody
             notices, because nothing breaks - the person simply keeps seeing
             information they no longer need. An access review is the scheduled
             moment somebody looks.

             This panel was added because a reviewer asked what the screen was
             for after reading the empty state. If the question gets asked, the
             screen has not answered it. --}}
        <div class="alert alert-info" role="note">
            <svg class="icon" aria-hidden="true"><use href="#i-search-check"/></svg>
            <span>
                <strong>What this is for.</strong>
                Access accumulates quietly - somebody changes team and keeps their old
                domain, a contractor finishes and keeps their role, a grant made during an
                incident is never taken back. Nothing breaks, so nobody notices. A review
                takes a snapshot of who currently holds which additional roles and business
                domains, asks a person to decide <em>keep</em> or <em>revoke</em> on each
                one, and then carries out the revocations. Most organisations run one every
                quarter.
            </span>
        </div>

        <section class="card panel" aria-labelledby="new-review">
            <div class="panel-head">
                <h2 class="panel-title" id="new-review">
                    <svg class="icon" aria-hidden="true"><use href="#i-search-check"/></svg>
                    Start a review
                </h2>
            </div>
            <form class="inline-form" method="POST" action="{{ route('admin.access-reviews.store') }}" style="padding:0">
                @csrf
                <div class="field">
                    <label class="field-label" for="name">Name<span class="field-required" aria-hidden="true">*</span></label>
                    <input class="input" type="text" id="name" name="name" value="{{ old('name') }}"
                           placeholder="Quarterly access review"
                           @error('name') aria-invalid="true" aria-describedby="name-message" @enderror>
                    <p class="field-message" id="name-message">@error('name'){{ $message }}@enderror</p>
                </div>
                <div class="field">
                    <label class="field-label" for="due_at">Due</label>
                    <input class="input" type="date" id="due_at" name="due_at" value="{{ old('due_at') }}">
                </div>
                <button type="submit" class="btn btn-solid btn-primary" data-async>
                    <span class="btn-label">Create draft</span>
                </button>
            </form>
            <p class="field-help">
                A review starts as a draft. Opening it takes a snapshot of everyone's access at
                that moment, and that snapshot is what gets reviewed - so a change made
                afterwards does not quietly alter what was approved.
            </p>
            <p class="field-help">
                Nothing is revoked until you decide every item and then apply the review. A
                platform role is deliberately not reviewed here: changing somebody's role has
                its own rules, including that the last System Administrator cannot be removed,
                and a bulk decision screen would route around them.
            </p>
        </section>

        <div class="card">
            @if ($reviews->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-search-check"/></svg>
                    <span class="empty-title">No reviews yet</span>
                    {{-- An empty state says what will be here and what has to
                         happen first. It should also say whether there is
                         anything to review, because on a new instance the
                         honest answer is usually "not yet". --}}
                    <span class="empty-note">
                        Start one above when people have accumulated access worth checking.
                        On a new instance there is usually nothing to review yet - reviews
                        cover additional roles and business domain access, and neither exists
                        until you have granted some.
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Access reviews</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">Review</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="cell-numeric">Items</th>
                                <th scope="col">Due</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reviews as $review)
                                <tr>
                                    <th scope="row" class="cell-heading">
                                        {{ $review->name }}
                                        @if ($review->opened_at)
                                            <span class="cell-note">Snapshot taken {{ $review->opened_at->toFormattedDateString() }}</span>
                                        @endif
                                    </th>
                                    <td>
                                        <span class="{{ $review->status->badgeClass() }}">{{ $review->status->label() }}</span>
                                        {{-- The state worth surfacing on its own. --}}
                                        @if ($review->isCompleted() && $review->applied_at === null && $review->pendingRevocationCount() > 0)
                                            <span class="cell-note">Decided, not yet applied</span>
                                        @endif
                                    </td>
                                    <td class="cell-numeric">{{ $review->items_count }}</td>
                                    <td>{{ $review->due_at?->toFormattedDateString() ?? '-' }}</td>
                                    <td>
                                        <a class="btn btn-secondary btn-small" href="{{ route('admin.access-reviews.show', $review) }}">
                                            <svg class="icon" aria-hidden="true"><use href="#i-eye"/></svg>
                                            <span class="btn-label">Open</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
