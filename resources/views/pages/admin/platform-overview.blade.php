{{--
    ADM-001 Platform Overview - the Administration landing page.

    MENU_STRUCTURE.md section 12.1 lists eight items under Platform Overview.
    All eight are sections of THIS page rather than eight rail entries, because
    they are eight views of one question and eight clicks to assemble one answer
    is not a control plane. Where each one landed:

      Setup Progress ................ the setup journey, below
      Environment Health ............ the health checks
      Security & Sovereignty Status . the Entra, data protection and
                                      sovereignty checks in the same list
      Pending Actions ............... "Needs your attention"
      Failed Automations ............ the failures list on Diagnostics, which is
                                      where the correlation ids to quote are
      Recent Changes ................ "Recent changes"
      Data Health ................... a step in the journey; there is no data
                                      estate to report on until the Fabric
                                      release builds one
      Intelligence Health ........... the same, for semantic intelligence

    Nothing on this page may expose a credential: every detail string comes from
    HealthProbe, which redacts, and every audit summary was redacted at write.
--}}
@extends('layouts.shell')

@section('title', 'Platform Overview · '.config('app.name'))
@section('page-title', 'Platform Overview')
@section('page-subtitle', 'Whether SemantIQ is working, and what to set up next.')

@section('content')
    <div class="stack">

        {{-- The answer to "is it working", stated once so it does not have to be
             assembled from seven rows. --}}
        <div class="card health-summary">
            <svg class="icon" aria-hidden="true"><use href="#i-heart-pulse"/></svg>
            <div class="health-summary-text">
                <span class="health-summary-title">
                    Platform status
                    <span class="{{ $overall->badgeClass() }}">{{ $overall->label() }}</span>
                </span>
                <span class="health-summary-note">
                    {{ $organisation?->name ?? 'No organisation resolved' }}
                    &middot; {{ $environment }}
                    &middot; version {{ $version }}
                </span>
            </div>
        </div>

        {{-- Pending Actions. The same facts as the health list, restated as a
             to-do list: an administrator arrives wanting either "is it working"
             or "what do I do", and one shape cannot answer both well. --}}
        <section class="card panel" aria-labelledby="pending-heading">
            <div class="panel-head">
                <h2 class="panel-title" id="pending-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-inbox"/></svg>
                    Needs your attention
                </h2>
                <span class="badge {{ $pending === [] ? 'badge-success' : 'badge-warning' }}">
                    {{ count($pending) }}
                </span>
            </div>

            @if ($pending === [])
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                    <span class="empty-title">Nothing outstanding</span>
                    <span class="empty-note">Every check the platform can run by itself is healthy.</span>
                </div>
            @else
                <ul class="attention-list">
                    @foreach ($pending as $check)
                        <li class="attention-item">
                            <span class="{{ $check->state->badgeClass() }}">{{ $check->state->label() }}</span>
                            <span>
                                <strong class="attention-name">{{ $check->name }}</strong>
                                <span class="cell-note">{{ $check->detail }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Environment Health, and Security and Sovereignty Status. --}}
        <section class="card" aria-labelledby="health-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="health-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-activity"/></svg>
                    Health checks
                </h2>
                <a class="btn btn-secondary btn-small" href="{{ route('admin.system.diagnostics') }}">
                    <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
                    Diagnostics
                </a>
            </div>
            @include('partials.health-table', ['checks' => $checks])
        </section>

        {{-- Setup Progress. --}}
        <section class="card" aria-labelledby="journey-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="journey-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-list-check"/></svg>
                    Setup progress
                </h2>
            </div>
            <ol class="journey">
                @foreach ($steps as $index => $step)
                    <li class="journey-step">
                        <span class="journey-number" aria-hidden="true">{{ $index + 1 }}</span>
                        <div class="journey-body">
                            <span class="journey-name">{{ $step['name'] }}</span>
                            <span class="domain-desc">{{ $step['detail'] }}</span>
                            <span class="journey-meta">
                                {{-- Automated or guided is the thing an
                                     administrator most needs to know before
                                     starting: one costs a click, the other
                                     costs a trip to a Microsoft portal and a
                                     tenant administrator's time. --}}
                                <span>{{ $step['automated'] ? 'Automated by SemantIQ' : 'Guided, needs a Microsoft admin' }}</span>
                                <span>Requires {{ $step['role'] }}</span>
                            </span>
                        </div>
                        <span class="badge">Not started</span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Recent Changes. --}}
        <section class="card" aria-labelledby="changes-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="changes-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-clock"/></svg>
                    Recent changes
                </h2>
            </div>

            @if ($recentChanges->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-clock"/></svg>
                    <span class="empty-title">Nothing recorded yet</span>
                    <span class="empty-note">
                        Every configuration change is audited from the moment it is made.
                        This list fills as the platform is set up.
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">The most recent audited changes</caption>
                        <thead>
                            <tr>
                                <th scope="col">When</th>
                                <th scope="col">Action</th>
                                <th scope="col">Who</th>
                                <th scope="col">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentChanges as $event)
                                <tr>
                                    <td>{{ $event->occurred_at->diffForHumans() }}</td>
                                    <td>
                                        {{ $event->action }}
                                        @if ($event->resource_id)
                                            <span class="cell-note">{{ $event->resource_id }}</span>
                                        @endif
                                    </td>
                                    {{-- The label rather than the relation, so
                                         the trail still reads after the account
                                         row is gone. A cell is never left blank:
                                         the template requires a placeholder. --}}
                                    <td>{{ $event->actor_label ?? 'System' }}</td>
                                    <td><span class="{{ $event->outcome->badgeClass() }}">{{ $event->outcome->label() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

    </div>
@endsection
