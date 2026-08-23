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
                that moment, and the snapshot is what gets reviewed.
            </p>
        </section>

        <div class="card">
            @if ($reviews->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-search-check"/></svg>
                    <span class="empty-title">No reviews yet</span>
                    <span class="empty-note">
                        An access review lists every additional role and business domain somebody
                        holds, and asks whether they still need it.
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
