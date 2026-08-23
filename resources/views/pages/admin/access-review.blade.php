{{--
    ADM-008, one review.

    EACH DECISION POSTS ON ITS OWN. Plan decision D4 chose React here to hold a
    session's decisions before one submit; building it out, that turns out to be
    the wrong shape. Every decision is an auditable event, so batching them
    means the trail records who pressed submit rather than who decided what, and
    a browser closed mid-review loses everything already decided. One form per
    row is better evidence and more robust. Recorded in the plan for review.

    An undecided item stays undecided. It is never treated as an implicit
    "keep", because a review where half the items were ignored is a finding, and
    `complete()` refuses while any remain.
--}}
@extends('layouts.shell')

@section('title', $review->name.' · '.config('app.name'))
@section('page-title', $review->name)
@section('page-subtitle', $review->description ?: 'Decide whether each grant is still needed.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <div class="card health-summary">
            <svg class="icon" aria-hidden="true"><use href="#i-search-check"/></svg>
            <div class="health-summary-text">
                <span class="health-summary-title">
                    Review status
                    <span class="{{ $review->status->badgeClass() }}">{{ $review->status->label() }}</span>
                </span>
                <span class="health-summary-note">
                    @if ($review->isDraft())
                        No snapshot taken yet.
                    @else
                        {{ $items->count() }} grant{{ $items->count() === 1 ? '' : 's' }} in the snapshot
                        &middot; {{ $undecided }} still undecided
                        @if ($pendingRevocations > 0)
                            &middot; {{ $pendingRevocations }} revocation{{ $pendingRevocations === 1 ? '' : 's' }} not yet carried out
                        @endif
                    @endif
                </span>
            </div>
        </div>

        {{-- The workflow controls, each appearing only in the state it belongs
             to, so the screen offers one next step rather than five buttons of
             which four fail. --}}
        <section class="card panel">
            <div class="panel-head">
                <h2 class="panel-title">
                    <svg class="icon" aria-hidden="true"><use href="#i-workflow"/></svg>
                    Next step
                </h2>
            </div>

            @if ($review->isDraft())
                <p class="field-help">
                    Opening takes a snapshot of everyone's additional roles and business domain
                    access as it is right now. That snapshot is what you review, and it is taken
                    once.
                </p>
                <form method="POST" action="{{ route('admin.access-reviews.open', $review) }}">
                    @csrf
                    <button type="submit" class="btn btn-solid btn-primary" data-async>
                        <span class="btn-label">Open review and take the snapshot</span>
                    </button>
                </form>
            @elseif ($review->isOpen())
                <p class="field-help">
                    Decide every item below. A review that records no decision for an item is not
                    evidence that the access was approved, so all {{ $undecided }} remaining must
                    be decided before it can be completed.
                </p>
                <form method="POST" action="{{ route('admin.access-reviews.complete', $review) }}">
                    @csrf
                    <button type="submit" class="btn btn-solid btn-primary" data-async @if($undecided > 0) disabled @endif>
                        <span class="btn-label">Complete review</span>
                    </button>
                </form>
            @elseif ($review->isCompleted() && $pendingRevocations > 0)
                <p class="field-help">
                    The decisions are recorded but the revocations have not been carried out.
                    Applying removes each revoked grant through the same checks and the same
                    audit trail as removing it by hand.
                </p>
                <form method="POST" action="{{ route('admin.access-reviews.apply', $review) }}">
                    @csrf
                    <button type="submit" class="btn btn-solid btn-primary" data-async>
                        <span class="btn-label">Apply {{ $pendingRevocations }} revocation{{ $pendingRevocations === 1 ? '' : 's' }}</span>
                    </button>
                </form>
            @else
                <p class="field-help">
                    Nothing further to do. This review is {{ strtolower($review->status->label()) }}
                    @if ($review->applied_at) and was applied on {{ $review->applied_at->toFormattedDateString() }} @endif.
                </p>
            @endif
        </section>

        @if (! $review->isDraft())
            <div class="card">
                @if ($items->isEmpty())
                    <div class="empty">
                        <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                        <span class="empty-title">Nothing to review</span>
                        <span class="empty-note">
                            Nobody held an additional role or a business domain when the snapshot
                            was taken. A platform role on its own is not reviewed here.
                        </span>
                    </div>
                @else
                    <div class="table-scroll">
                        <table class="data-table">
                            <caption class="visually-hidden">Grants awaiting a decision</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="col-primary">Person and grant</th>
                                    <th scope="col">Kind</th>
                                    <th scope="col">Decision</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <th scope="row" class="cell-heading">
                                            {{ $item->user?->name ?? 'Deleted account' }}
                                            {{-- The label as it was WHEN THE SNAPSHOT WAS
                                                 TAKEN, so the evidence survives a later
                                                 rename or revocation. --}}
                                            <span class="cell-note">{{ $item->subject_label }}</span>
                                        </th>
                                        <td>
                                            <span class="badge">{{ $item->isRole() ? 'Role' : 'Business domain' }}</span>
                                        </td>
                                        <td>
                                            <span class="{{ $item->decision->badgeClass() }}">{{ $item->decision->label() }}</span>
                                            @if ($item->applied)
                                                <span class="cell-note">Carried out</span>
                                            @endif
                                            @if ($item->note)
                                                <span class="cell-note">{{ $item->note }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($review->isOpen())
                                                <form method="POST"
                                                      action="{{ route('admin.access-reviews.decide', [$review, $item]) }}"
                                                      class="row-form">
                                                    @csrf
                                                    <label class="field-label visually-hidden" for="note-{{ $item->id }}">
                                                        Note for {{ $item->subject_label }}
                                                    </label>
                                                    <input class="input" type="text" id="note-{{ $item->id }}"
                                                           name="note" maxlength="500" placeholder="Note (optional)">
                                                    <span class="pill-row">
                                                        @foreach ($decisions as $decision)
                                                            <button type="submit" name="decision" value="{{ $decision->value }}"
                                                                    class="btn btn-secondary btn-small @if($decision === \App\Modules\Identity\Enums\ReviewDecision::Revoke) is-danger @endif"
                                                                    data-async>
                                                                <span class="btn-label">{{ $decision->label() }}</span>
                                                            </button>
                                                        @endforeach
                                                    </span>
                                                </form>
                                            @else
                                                <span class="cell-empty">
                                                    {{ $item->decidedBy?->name ? 'Decided by '.$item->decidedBy->name : '-' }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        @unless ($review->isCompleted())
            <section class="card panel">
                <div class="panel-head">
                    <h2 class="panel-title">
                        <svg class="icon" aria-hidden="true"><use href="#i-x-circle"/></svg>
                        Cancel this review
                    </h2>
                </div>
                <p class="field-help">
                    A cancelled review is kept rather than deleted. That a review was started and
                    abandoned is information an auditor is entitled to.
                </p>
                <form class="inline-form" method="POST" action="{{ route('admin.access-reviews.cancel', $review) }}" style="padding:0">
                    @csrf
                    <div class="field">
                        <label class="field-label" for="cancel-reason">Reason</label>
                        <input class="input" type="text" id="cancel-reason" name="reason" maxlength="200" placeholder="Optional">
                    </div>
                    <button type="submit" class="btn btn-secondary is-danger" data-async>
                        <span class="btn-label">Cancel review</span>
                    </button>
                </form>
            </section>
        @endunless
    </div>
@endsection
