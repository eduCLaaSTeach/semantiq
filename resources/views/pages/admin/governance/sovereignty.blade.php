{{--
    ADM-015 Data Sovereignty Profile.

    Where this organisation's data is stored, processed, processed by AI and
    backed up, and whether anything crosses a border.

    BACKUPS ARE SHOWN AS THEIR OWN ANSWER, not folded into storage. Backups
    routinely leave the country the server sits in, which moves data out of a
    geography without the server moving. All three were asked separately for
    this deployment and all three came back Singapore - SEC-DEC-036 - and the
    screen keeps the distinction that made the question worth asking.

    THE SEEDED FIRST DRAFT IS NEVER SHOWN AS APPROVED. SEC-DEC-068. It carries
    the draft badge, its provenance note says where the values came from, and
    the panel above reads Not Configured until a person approves it. A green
    tick over a sovereignty position nobody approved would be the same false
    healthy gate 3 shipped over an untracked credential estate.
--}}
@extends('layouts.shell')

@section('title', 'Sovereignty Profile · '.config('app.name'))
@section('page-title', 'Sovereignty Profile')
@section('page-subtitle', 'Where this organisation stores, processes and backs up its data, and whether any of it crosses a border.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        @unless ($storageReady)
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                <span>
                    <strong>Sovereignty storage has not been initialised.</strong>
                    {{ $storageBlocker }}
                </span>
            </div>
        @endunless

        {{-- The border warning goes above everything. A position that moves
             data across a border is the single thing a reader most needs to
             notice, and noticing it at the bottom of a form is noticing it too
             late. --}}
        @if ($crossesABorder)
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-globe"/></svg>
                <span>
                    <strong>This position moves data across a border.</strong>
                    Either a cross-geo switch is on, replication crosses geographies, or the four
                    geographies below do not all name the same place. CLAUDE.md requires cross-geo
                    storage, processing and AI or conversation history to be off unless an approved
                    exception says otherwise.
                </span>
            </div>
        @endif

        <section class="card" aria-labelledby="in-force-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="in-force-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-globe"/></svg>
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
                    @foreach ($inForce->geographies() as $question => $value)
                        <div class="record-row">
                            <span class="record-label">{{ $question }}</span>
                            <span class="record-value">
                                {{ $geographies[$value] ?? 'Not Configured' }}
                            </span>
                        </div>
                    @endforeach
                    <div class="record-row">
                        <span class="record-label">Replication outside the geography</span>
                        <span class="record-value">
                            {{ $replicationChoices[$inForce->external_replication] ?? 'Not Configured' }}
                        </span>
                    </div>
                    <div class="record-row">
                        <span class="record-label">Evidence reference</span>
                        <span class="record-value">{{ $inForce->evidence_reference ?: 'Not Configured' }}</span>
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
                    <span class="empty-title">No sovereignty profile has been approved</span>
                    <span class="empty-note">
                        Nobody has approved a position on where this organisation's data may live.
                        @if ($draft)
                            A draft exists below, seeded from the hosting facts this deployment has
                            already confirmed - but a draft is not a decision, and nothing in SemantIQ
                            acts on it until somebody approves it.
                        @endif
                    </span>
                </div>
            @endif
        </section>

        @if ($gaps !== [])
            <section class="card" aria-labelledby="gaps-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="gaps-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-alert-octagon"/></svg>
                        Still to answer
                        <span class="badge badge-warning">{{ count($gaps) }}</span>
                    </h2>
                </div>
                <div class="record-list">
                    <ul class="field-help">
                        @foreach ($gaps as $gap)
                            <li>{{ $gap }}</li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        @if ($storageReady && $editing)
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

                <div class="record-list">
                    @if ($draft)
                        <p class="field-help">{{ $draft->status->explanation() }}</p>
                    @else
                        {{-- No draft, so the form shows the version in force.
                             Without this the screen went permanently read-only
                             the moment somebody approved the first version:
                             the seeded draft was gone and nothing opened
                             another. --}}
                        <p class="field-help">
                            There is no draft open, so this form shows version {{ $editing->version }} -
                            the version in force. Saving it starts version {{ $editing->version + 1 }} as
                            a draft and leaves version {{ $editing->version }} in force until the new one
                            is approved.
                        </p>
                    @endif
                    @if ($editing->source_note)
                        {{-- Provenance, shown rather than buried. It is what
                             lets a reader tell a confirmed fact from a
                             typed-in one. --}}
                        <div class="record-row">
                            <span class="record-label">Where these values came from</span>
                            <span class="record-value">{{ $editing->source_note }}</span>
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.governance.sovereignty.update') }}" class="settings-form">
                    @csrf
                    @method('PUT')

                    <div class="settings-fields">
                        @foreach ([
                            'storage_geography' => ['Storage geography', 'Where the data itself sits.'],
                            'processing_geography' => ['Processing geography', 'Where it is processed - which is not always where it sits.'],
                            'ai_processing_geography' => ['AI processing geography', 'Where prompts, responses and embeddings are processed. Leave undetermined until an AI service is actually provisioned.'],
                            'backup_geography' => ['Backup geography', 'Where the backups live. Ask this separately: backups routinely leave the country the server is in.'],
                        ] as $field => $meta)
                            <div class="field">
                                <label class="field-label" for="{{ $field }}">{{ $meta[0] }}</label>
                                <select class="input" id="{{ $field }}" name="{{ $field }}">
                                    @foreach ($geographies as $value => $label)
                                        <option value="{{ $value }}"
                                            @selected(old($field, $editing->{$field}) === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="field-help">{{ $meta[1] }}</p>
                                <p class="field-message" id="{{ $field }}-message">@error($field){{ $message }}@enderror</p>
                            </div>
                        @endforeach

                        <div class="field">
                            <label class="field-label" for="external_replication">Replication outside the geography</label>
                            <select class="input" id="external_replication" name="external_replication">
                                @foreach ($replicationChoices as $value => $label)
                                    <option value="{{ $value }}"
                                        @selected(old('external_replication', $editing->external_replication) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="field-message" id="external_replication-message">@error('external_replication'){{ $message }}@enderror</p>
                        </div>

                        {{-- Every switch ships off. CLAUDE.md requires cross-geo
                             processing, storage and AI or conversation history to
                             default OFF, and the database default says so too. --}}
                        @foreach ([
                            'cross_geo_storage' => 'Data may be stored outside its geography',
                            'cross_geo_processing' => 'Data may be processed outside its geography',
                            'cross_geo_ai' => 'AI may process data outside its geography',
                            'cross_geo_conversation_history' => 'Conversation history may be held outside its geography',
                        ] as $switch => $label)
                            <div class="field">
                                <label class="checkbox">
                                    <input type="checkbox" name="{{ $switch }}" value="1"
                                           @checked(old($switch, $editing->{$switch}))>
                                    <span>{{ $label }}</span>
                                </label>
                            </div>
                        @endforeach

                        <div class="field">
                            <label class="field-label" for="evidence_reference">Evidence reference</label>
                            <input class="input" type="text" id="evidence_reference" name="evidence_reference"
                                   value="{{ old('evidence_reference', $editing->evidence_reference) }}"
                                   @error('evidence_reference') aria-invalid="true" aria-describedby="evidence_reference-message" @enderror>
                            <p class="field-help">Where the proof of these geographies is held - a hosting contract, a provider attestation. A pointer, never the document and never a credential.</p>
                            <p class="field-message" id="evidence_reference-message">@error('evidence_reference'){{ $message }}@enderror</p>
                        </div>

                        <div class="field">
                            <label class="field-label" for="notes">Notes</label>
                            <textarea class="input" id="notes" name="notes" rows="3"
                                      @error('notes') aria-invalid="true" aria-describedby="notes-message" @enderror>{{ old('notes', $editing->notes) }}</textarea>
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

        @endif

        @if ($storageReady && $draft)
            <section class="card" aria-labelledby="approve-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="approve-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-badge-check"/></svg>
                        Approve version {{ $draft->version }}
                    </h2>
                </div>

                <div class="record-list">
                    <p class="field-help">
                        Approving makes this version the one in force and freezes it. Changing it later
                        creates a new version and supersedes this one, so where the data was on any given
                        date stays answerable.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.governance.sovereignty.approve') }}" class="settings-form">
                    @csrf

                    <div class="settings-fields">
                        <div class="field">
                            <label class="field-label" for="reason">Reason<span class="field-required" aria-hidden="true">*</span></label>
                            <input class="input" type="text" id="reason" name="reason" required
                                   value="{{ old('reason') }}"
                                   @error('reason') aria-invalid="true" aria-describedby="reason-message" @enderror>
                            <p class="field-help">Why this position is being approved, and who confirmed the geographies. It goes into the audit trail.</p>
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
                        <caption class="visually-hidden">Every version of the sovereignty profile, newest first</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-label">Version</th>
                                <th scope="col">State</th>
                                <th scope="col">Storage</th>
                                <th scope="col">Backups</th>
                                <th scope="col">Approved</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $version)
                                <tr>
                                    <th scope="row" class="cell-heading">{{ $version->version }}</th>
                                    <td><span class="{{ $version->status->badge() }}">{{ $version->status->label() }}</span></td>
                                    <td>{{ $geographies[$version->storage_geography] ?? 'Not Configured' }}</td>
                                    <td>{{ $geographies[$version->backup_geography] ?? 'Not Configured' }}</td>
                                    <td>
                                        @if ($version->approved_at)
                                            {{ $version->approved_at->format('j M Y') }}
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
