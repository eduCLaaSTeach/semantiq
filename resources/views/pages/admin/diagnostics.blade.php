{{--
    ADM-024 Diagnostics.

    Safe troubleshooting: enough to tell whether the problem is the application,
    the database, the queue, the scheduler or Microsoft, and enough to give
    support a precise reference - without any of the things ADM-024's "never
    expose" list names.

    WHAT IS DELIBERATELY ABSENT FROM THIS PAGE, and must stay absent: `.env`
    contents, passwords, API credentials, tokens, secret values, production row
    data, host names, database names and file paths. Nothing here is assembled
    in the template. Every fact comes from HealthProbe, which limits itself to
    driver names and redacts every message it did not write itself, and from
    correlation ids, which are random and carry no information at all.

    The extended fact set is behind the platform.extended_diagnostics flag: even
    redacted, a description of the runtime is worth something to somebody who
    has not seen one.
--}}
@extends('layouts.shell')

@section('title', 'Diagnostics · '.config('app.name'))
@section('page-title', 'Diagnostics')
@section('page-subtitle', 'What is working, what is not, and the reference to quote when you ask for help.')

@section('content')
    <div class="stack">

        <div class="card health-summary">
            <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
            <div class="health-summary-text">
                <span class="health-summary-title">
                    Platform status
                    <span class="{{ $overall->badgeClass() }}">{{ $overall->label() }}</span>
                </span>
                <span class="health-summary-note">
                    This page reference:
                    <span class="cell-reference">{{ $correlationId }}</span>
                </span>
            </div>
        </div>

        <section class="card" aria-labelledby="facts-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="facts-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-server"/></svg>
                    Runtime
                </h2>
                <a class="btn btn-secondary btn-small" href="{{ route('admin.system.feature-flags') }}">
                    <svg class="icon" aria-hidden="true"><use href="#i-flag"/></svg>
                    Feature flags
                </a>
            </div>
            <div class="table-scroll">
                <table class="data-table">
                    <caption class="visually-hidden">Runtime facts about this instance</caption>
                    <thead>
                        <tr>
                            <th scope="col">Fact</th>
                            <th scope="col">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($facts as $name => $value)
                            <tr>
                                <th scope="row" class="cell-heading">{{ $name }}</th>
                                <td>{{ $value !== '' ? $value : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card" aria-labelledby="checks-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="checks-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-activity"/></svg>
                    Connectivity
                </h2>
            </div>
            @include('partials.health-table', ['checks' => $checks])
        </section>

        {{-- Failed Automations, and the correlation ids to quote. The ids are
             the point: they are random, carry no information, and give support
             something precise to search for. --}}
        <section class="card" aria-labelledby="failures-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="failures-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                    Recent failures and refusals
                </h2>
            </div>

            @if ($failures->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                    <span class="empty-title">Nothing to report</span>
                    <span class="empty-note">
                        No audited action has failed or been refused. Refusals are recorded
                        as well as failures, because a trail of successes cannot show an
                        attempt that was blocked.
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Recent failed and refused actions with their references</caption>
                        <thead>
                            <tr>
                                <th scope="col">When</th>
                                <th scope="col">Action</th>
                                <th scope="col">Result</th>
                                <th scope="col">Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($failures as $event)
                                <tr>
                                    <td>{{ $event->occurred_at->diffForHumans() }}</td>
                                    <td>
                                        {{ $event->action }}
                                        {{-- The reason was scrubbed on the way in.
                                             A cell is never left blank. --}}
                                        <span class="cell-note">{{ $event->reason ?? 'No reason recorded' }}</span>
                                    </td>
                                    <td><span class="{{ $event->outcome->badgeClass() }}">{{ $event->outcome->label() }}</span></td>
                                    <td class="cell-reference">{{ $event->correlation_id ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

    </div>
@endsection
