{{--
    ADM-013 Audit Logs. DEC-004.

    ONE READ-ONLY SCREEN with four functional views as filter presets. There is
    no write control anywhere on this page and there should never be: a screen
    that could change an audit event would defeat the database triggers that
    make the trail evidence.

    THE NETWORK COLUMN IS ABSENT, NOT MASKED, for a reader without
    `admin.audit.view_network`. `AuditLogQuery` does not select it, so there is
    no value in the response to hide - a masked value has already been read out
    of the database and is one careless dump away from being visible.

    The presets deliberately OVERLAP rather than partitioning the table. A
    reader who picks the wrong view must still find the event by widening to All
    Events.
--}}
@extends('layouts.shell')

@section('title', 'Audit Logs · '.config('app.name'))
@section('page-title', 'Audit Logs')
@section('page-subtitle', 'What happened, who did it, when, and why.')

@section('content')
    <div class="stack">

        {{-- The view presets. Links rather than a select, so a view is a URL
             somebody can share, bookmark, or be linked to from elsewhere -
             which is what lets a future Governance Overview drill through to
             ?view=security instead of the product growing four audit screens. --}}
        <section class="card" aria-labelledby="views-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="views-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-funnel"/></svg>
                    View
                </h2>
            </div>
            <div class="record-list">
                <div class="pill-row">
                    @foreach ($views as $key => $definition)
                        <a class="btn {{ $key === $view ? 'btn-solid btn-primary' : 'btn-secondary' }} btn-small"
                           href="{{ route('admin.governance.audit', ['view' => $key]) }}"
                           @if ($key === $view) aria-current="page" @endif>
                            <span class="btn-label">{{ $definition['label'] }}</span>
                        </a>
                    @endforeach
                </div>
                <p class="field-help">{{ $viewDefinition['help'] }}</p>
            </div>
        </section>

        <section class="card" aria-labelledby="filters-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="filters-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
                    Refine
                </h2>
            </div>

            {{-- GET, not POST. A filtered trail is a URL worth quoting in an
                 incident note. --}}
            <form method="GET" action="{{ route('admin.governance.audit') }}" class="settings-form">
                <input type="hidden" name="view" value="{{ $view }}">

                <div class="settings-fields">
                    <div class="field">
                        <label class="field-label" for="from">From</label>
                        <input class="input" type="date" id="from" name="from" value="{{ $filters['from'] }}">
                    </div>
                    <div class="field">
                        <label class="field-label" for="to">To</label>
                        <input class="input" type="date" id="to" name="to" value="{{ $filters['to'] }}">
                    </div>
                    <div class="field">
                        <label class="field-label" for="actor">Actor</label>
                        <input class="input" type="text" id="actor" name="actor" value="{{ $filters['actor'] }}">
                        <p class="field-help">Matches the actor recorded at the time, which is their email address. It is captured as text, so it still reads correctly after the account is deleted.</p>
                    </div>
                    <div class="field">
                        <label class="field-label" for="action">Action</label>
                        <input class="input" type="text" id="action" name="action" value="{{ $filters['action'] }}">
                        <p class="field-help">Part of the dotted name, such as <code>approved</code> or <code>sovereignty</code>.</p>
                    </div>
                    <div class="field">
                        <label class="field-label" for="module">Module</label>
                        <select class="input" id="module" name="module">
                            <option value="">Any</option>
                            @foreach ($modules as $module)
                                <option value="{{ $module }}" @selected($filters['module'] === $module)>{{ $module }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="outcome">Outcome</label>
                        <select class="input" id="outcome" name="outcome">
                            <option value="">Any</option>
                            @foreach ($outcomes as $outcome)
                                <option value="{{ $outcome->value }}" @selected($filters['outcome'] === $outcome->value)>{{ ucfirst($outcome->value) }}</option>
                            @endforeach
                        </select>
                        <p class="field-help">A denial is evidence and is recorded, so this is how an incident review finds refusals.</p>
                    </div>
                    <div class="field">
                        <label class="field-label" for="resource_type">Resource type</label>
                        <select class="input" id="resource_type" name="resource_type">
                            <option value="">Any</option>
                            @foreach ($resourceTypes as $type)
                                <option value="{{ $type }}" @selected($filters['resource_type'] === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="correlation_id">Correlation ID</label>
                        <input class="input" type="text" id="correlation_id" name="correlation_id" value="{{ $filters['correlation_id'] }}">
                        <p class="field-help">Exact match. Pasting one shows every event from that single request.</p>
                    </div>
                    <div class="field">
                        <label class="field-label" for="reason">Reason</label>
                        <input class="input" type="text" id="reason" name="reason" value="{{ $filters['reason'] }}">
                    </div>
                </div>

                <div class="settings-foot">
                    <button type="submit" class="btn btn-solid btn-primary">
                        <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
                        <span class="btn-label">Apply</span>
                    </button>
                    @if ($anyFilterApplied)
                        <a class="btn btn-secondary" href="{{ route('admin.governance.audit', ['view' => $view]) }}">
                            <span class="btn-label">Clear filters</span>
                        </a>
                    @endif
                </div>
            </form>
        </section>

        <section class="card" aria-labelledby="events-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="events-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-list-check"/></svg>
                    {{ $viewDefinition['label'] }}
                    <span class="badge">{{ $events->total() }}</span>
                </h2>
            </div>

            @if ($events->isEmpty())
                {{-- Two different empty states, because "nothing matched your
                     filter" and "nothing has been recorded" are opposite facts
                     and reading the second as the first would be alarming. --}}
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
                    @if ($anyFilterApplied || $view !== 'all')
                        <span class="empty-title">Nothing matches</span>
                        <span class="empty-note">
                            No event matches this view and these filters. The trail is not empty - widen
                            the filters, or switch to All Events, to see what is recorded.
                        </span>
                    @else
                        <span class="empty-title">No events recorded</span>
                        <span class="empty-note">
                            Nothing has been written to the audit trail on this deployment yet. Signing in
                            and changing something will produce the first entries.
                        </span>
                    @endif
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Audit events, newest first</caption>
                        <thead>
                            <tr>
                                <th scope="col">When</th>
                                <th scope="col" class="col-primary">What</th>
                                <th scope="col">Who</th>
                                <th scope="col">Outcome</th>
                                @if ($maySeeNetwork)
                                    <th scope="col">From</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($events as $event)
                                <tr>
                                    <td class="cell-numeric">
                                        {{ $event->occurred_at?->format('j M Y') }}
                                        <span class="cell-note">{{ $event->occurred_at?->format('H:i:s') }} UTC</span>
                                    </td>
                                    <th scope="row" class="cell-heading">
                                        {{ $event->action }}
                                        <span class="cell-note">
                                            {{ $event->module }}@if ($event->resource_type) &middot; {{ $event->resource_type }}@if ($event->resource_id) #{{ $event->resource_id }}@endif @endif
                                            @if ($event->reason)
                                                <br>{{ $event->reason }}
                                            @endif
                                        </span>
                                    </th>
                                    <td>
                                        {{ $event->actor_label ?: 'System' }}
                                        {{-- `cell-note` wraps it, because
                                             `cell-reference` alone is inline and
                                             the correlation id ran straight on
                                             from the email address as one
                                             unreadable string. Found by looking
                                             at the rendered table. --}}
                                        <span class="cell-note">
                                            <span class="cell-reference">{{ $event->correlation_id }}</span>
                                        </span>
                                    </td>
                                    <td>
                                        @php($outcome = $event->outcome?->value)
                                        <span class="badge {{ $outcome === 'succeeded' ? 'badge-success' : ($outcome === 'denied' ? 'badge-warning' : 'badge-danger') }}">
                                            {{ ucfirst((string) $outcome) }}
                                        </span>
                                    </td>
                                    @if ($maySeeNetwork)
                                        {{-- Rendered only when the query selected it. One
                                             answer decides both, so a header can never sit
                                             over a column that was never fetched. --}}
                                        <td><span class="cell-reference">{{ $event->ip_address ?: '-' }}</span></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pager">
                    {{ $events->links() }}
                </div>
            @endif
        </section>

        @unless ($maySeeNetwork)
            <div class="alert alert-info" role="note">
                <svg class="icon" aria-hidden="true"><use href="#i-eye-off"/></svg>
                <span>
                    Network information is not shown. An IP address is personal data and sits behind a
                    separate permission held by System Administrators, so it is not read from the
                    database for this view at all.
                </span>
            </div>
        @endunless
    </div>
@endsection
