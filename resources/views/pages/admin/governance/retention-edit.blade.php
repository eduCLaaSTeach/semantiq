{{--
    PDPA-03, editing the retention policy for one category.

    THREE FIELDS ARE COMPLIANCE-OWNED AND SEMANTIQ SHIPS THEM EMPTY: the period,
    the basis and the lawful basis. Their help text says so explicitly, because
    a blank field with no explanation reads as an oversight, and an
    administrator who fills one in with a guess has recorded a guess as policy.
--}}
@extends('layouts.shell')

@section('title', $category->name.' retention · '.config('app.name'))
@section('page-title', $category->name)
@section('page-subtitle', 'How long this kind of personal data is kept, on what basis, and what happens at the end.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <div class="alert alert-info" role="note">
            <svg class="icon" aria-hidden="true"><use href="#i-alert-circle"/></svg>
            <span>
                <strong>Saving this deletes nothing.</strong>
                It records what the organisation intends. No sweep runs, and approving it records that a
                person agreed the period rather than switching anything on.
            </span>
        </div>

        @if ($policy && $policy->gaps() !== [])
            <section class="card" aria-labelledby="gaps-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="gaps-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-alert-octagon"/></svg>
                        Still to answer
                        <span class="badge badge-warning">{{ count($policy->gaps()) }}</span>
                    </h2>
                </div>
                <div class="record-list">
                    <ul class="field-help">
                        @foreach ($policy->gaps() as $gap)
                            <li>{{ $gap }}</li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        <section class="card" aria-labelledby="policy-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="policy-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-refresh"/></svg>
                    Retention policy
                </h2>
                @if ($policy)
                    <span class="{{ $policy->status->badge() }}">{{ $policy->status->label() }}</span>
                @else
                    <span class="badge">Nothing recorded</span>
                @endif
            </div>

            <div class="record-list">
                <div class="record-row">
                    <span class="record-label">Where this data lives</span>
                    <span class="record-value">
                        {{ $category->tables() === [] ? 'No tables named' : implode(', ', $category->tables()) }}
                    </span>
                </div>
                <div class="record-row">
                    <span class="record-label">Classification</span>
                    <span class="record-value">{{ $category->classification->label() }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.governance.retention.update', $category) }}" class="settings-form">
                @csrf
                @method('PUT')

                <div class="settings-fields">
                    <div class="field">
                        <label class="field-label" for="retention_months">Retention period, in months</label>
                        <input class="input" type="number" min="1" max="1200" id="retention_months" name="retention_months"
                               value="{{ old('retention_months', $policy?->retention_months) }}"
                               @error('retention_months') aria-invalid="true" aria-describedby="retention_months-message" @enderror>
                        <p class="field-help">Leave blank if it has not been decided. Blank reads as Not Configured, which is honest; SemantIQ will not invent a figure, and the seven years this repository once applied to everything was a position rather than a per-category decision.</p>
                        <p class="field-message" id="retention_months-message">@error('retention_months'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="start_event">Counted from</label>
                        <select class="input" id="start_event" name="start_event">
                            <option value="">Not Configured</option>
                            @foreach ($startEvents as $value => $label)
                                <option value="{{ $value }}" @selected(old('start_event', $policy?->start_event) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="field-help">Without this a period is unusable. "Three years" from account closure and from record creation are different dates, often years apart.</p>
                        <p class="field-message" id="start_event-message">@error('start_event'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="disposal_action">What happens at the end</label>
                        <select class="input" id="disposal_action" name="disposal_action">
                            <option value="">Not Configured</option>
                            @foreach ($disposalActions as $value => $label)
                                <option value="{{ $value }}" @selected(old('disposal_action', $policy?->disposal_action) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="field-help">Recorded as an intention. Nothing in SemantIQ carries it out.</p>
                        <p class="field-message" id="disposal_action-message">@error('disposal_action'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="lawful_basis">Lawful basis for holding it</label>
                        <input class="input" type="text" id="lawful_basis" name="lawful_basis"
                               value="{{ old('lawful_basis', $policy?->lawful_basis) }}"
                               @error('lawful_basis') aria-invalid="true" aria-describedby="lawful_basis-message" @enderror>
                        <p class="field-help">Compliance-owned. SemantIQ ships this blank on purpose: it is a legal judgement, and a plausible answer generated here would be a compliance claim nobody made.</p>
                        <p class="field-message" id="lawful_basis-message">@error('lawful_basis'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="basis">Basis for the period</label>
                        <textarea class="input" id="basis" name="basis" rows="3"
                                  @error('basis') aria-invalid="true" aria-describedby="basis-message" @enderror>{{ old('basis', $policy?->basis) }}</textarea>
                        <p class="field-help">Why that long - the obligation, guidance or contract it comes from. Also blank on purpose, and for the same reason.</p>
                        <p class="field-message" id="basis-message">@error('basis'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="owner">Accountable owner</label>
                        <input class="input" type="text" id="owner" name="owner"
                               value="{{ old('owner', $policy?->owner) }}"
                               @error('owner') aria-invalid="true" aria-describedby="owner-message" @enderror>
                        <p class="field-help">The person or role answerable for this category. A name or a job title, never a login or a key.</p>
                        <p class="field-message" id="owner-message">@error('owner'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="exception_rule">Exception rule</label>
                        <textarea class="input" id="exception_rule" name="exception_rule" rows="3"
                                  @error('exception_rule') aria-invalid="true" aria-describedby="exception_rule-message" @enderror>{{ old('exception_rule', $policy?->exception_rule) }}</textarea>
                        <p class="field-help">Anything that overrides the period - a legal hold, an ongoing dispute, a regulatory obligation. Free text, because the shape of an exception is not knowable in advance.</p>
                        <p class="field-message" id="exception_rule-message">@error('exception_rule'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="next_review_on">Next review</label>
                        <input class="input" type="date" id="next_review_on" name="next_review_on"
                               value="{{ old('next_review_on', $policy?->next_review_on?->toDateString()) }}"
                               @error('next_review_on') aria-invalid="true" aria-describedby="next_review_on-message" @enderror>
                        <p class="field-help">When somebody should look at this again. The screen flags it once the date passes; nothing else happens.</p>
                        <p class="field-message" id="next_review_on-message">@error('next_review_on'){{ $message }}@enderror</p>
                    </div>
                </div>

                <div class="settings-foot">
                    <button type="submit" class="btn btn-solid btn-primary">
                        <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                        <span class="btn-label">Save policy</span>
                    </button>
                    <a class="btn btn-secondary" href="{{ route('admin.governance.retention') }}">
                        <span class="btn-label">Cancel</span>
                    </a>
                </div>
            </form>
        </section>

        @if ($policy && $policy->hasPeriod())
            <section class="card" aria-labelledby="approve-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="approve-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-badge-check"/></svg>
                        Approve this policy
                    </h2>
                </div>

                <div class="record-list">
                    <p class="field-help">
                        Approving records that a person agreed this period. It switches nothing on.
                        Editing the policy afterwards returns it to draft, because a period that changed
                        after approval is not the period anybody approved.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.governance.retention.approve', $category) }}" class="settings-form">
                    @csrf

                    <div class="settings-fields">
                        <div class="field">
                            <label class="field-label" for="reason">Reason<span class="field-required" aria-hidden="true">*</span></label>
                            <input class="input" type="text" id="reason" name="reason" required value="{{ old('reason') }}"
                                   @error('reason') aria-invalid="true" aria-describedby="reason-message" @enderror>
                            <p class="field-help">Who confirmed this period, and against what. It goes into the audit trail.</p>
                            <p class="field-message" id="reason-message">@error('reason'){{ $message }}@enderror</p>
                        </div>
                    </div>

                    <div class="settings-foot">
                        <button type="submit" class="btn btn-solid btn-primary">
                            <svg class="icon" aria-hidden="true"><use href="#i-badge-check"/></svg>
                            <span class="btn-label">Approve policy</span>
                        </button>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
