{{--
    Security Overview.

    A read-only roll-up over ADM-009 to ADM-012. Decision D5: this leaf has no
    feature of its own in the Release 1 specification, so it summarises what the
    other four own and invents no policy and no control.

    There is no form and no action on this page. Everything an administrator can
    change is changed on the screen that owns it, and each panel links there
    rather than duplicating its controls - the same filter-not-fork rule the
    navigation follows.

    NOTHING IS REPORTED HEALTHY THAT COULD NOT BE CHECKED. Where a fact is not
    verifiable - an expiry date nobody entered, a provider this application does
    not call - the badge reads Not Verified, which is a different colour from
    green on purpose.
--}}
@extends('layouts.shell')

@section('title', 'Security Overview · '.config('app.name'))
@section('page-title', 'Security Overview')
@section('page-subtitle', 'How this deployment is protected, and what needs attention.')

@section('content')
    <div class="stack">

        <div class="card health-summary">
            <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
            <div class="health-summary-text">
                <span class="health-summary-title">
                    Security posture
                    <span class="{{ $overall->badgeClass() }}">{{ $overall->label() }}</span>
                </span>
                <span class="health-summary-note">
                    The worst of the four areas below. One critical finding among four healthy areas
                    is a critical posture.
                </span>
            </div>
        </div>

        {{-- Critical configuration gaps first: these have a specific fix, and
             the fix is usually not on any of these screens. --}}
        @if ($gaps !== [])
            <section class="card" aria-labelledby="gaps-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="gaps-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-alert-octagon"/></svg>
                        Configuration gaps
                        <span class="badge badge-warning">{{ count($gaps) }}</span>
                    </h2>
                </div>
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Things that are not set up</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">Gap</th>
                                <th scope="col">What it means</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($gaps as $gap)
                                <tr>
                                    <th scope="row" class="cell-heading">{{ $gap['title'] }}</th>
                                    <td>{{ $gap['detail'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="card" aria-labelledby="postures-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="postures-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-list-check"/></svg>
                    The four areas
                </h2>
            </div>
            <div class="table-scroll">
                <table class="data-table">
                    <caption class="visually-hidden">Authentication, session, application and secret posture</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="col-primary">Area</th>
                            <th scope="col">State</th>
                            <th scope="col">Now</th>
                            <th scope="col">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([
                            ['Authentication', 'ADM-009', $authentication, 'admin.security.authentication', 'i-fingerprint'],
                            ['Sessions', 'ADM-010', $sessions, 'admin.security.sessions', 'i-clock'],
                            ['Application security', 'ADM-011', $application, 'admin.security.api', 'i-code'],
                            ['Secret references', 'ADM-012', $secrets, 'admin.security.secrets', 'i-lock'],
                        ] as [$area, $feature, $posture, $route, $icon])
                            <tr>
                                <th scope="row" class="cell-heading">
                                    <a href="{{ route($route) }}">
                                        <svg class="icon" aria-hidden="true"><use href="#{{ $icon }}"/></svg>
                                        {{ $area }}
                                    </a>
                                    <span class="cell-note">{{ $feature }}</span>
                                </th>
                                <td><span class="{{ $posture['status']->badgeClass() }}">{{ $posture['status']->label() }}</span></td>
                                <td>
                                    {{ $posture['headline'] }}
                                    <span class="cell-note">{{ $posture['detail'] }}</span>
                                </td>
                                <td>
                                    @if ($posture['notes'] === [])
                                        -
                                    @else
                                        <ul>
                                            @foreach ($posture['notes'] as $note)
                                                <li>{{ $note }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card" aria-labelledby="expiring-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="expiring-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-hourglass"/></svg>
                    Expiring credentials
                </h2>
                <a class="btn btn-secondary btn-small" href="{{ route('admin.security.secrets') }}">
                    <svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>
                    Secret references
                </a>
            </div>

            @if ($expiring->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                    <span class="empty-title">Nothing expiring in the next {{ \App\Modules\Security\Enums\SecretStatus::EXPIRY_HORIZON_DAYS }} days</span>
                    <span class="empty-note">
                        This reflects the expiry dates recorded here. SemantIQ does not contact any provider
                        to confirm them, so a credential nobody recorded will not appear.
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Credentials expiring soon or already expired</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">Reference</th>
                                <th scope="col">State</th>
                                <th scope="col">Expires</th>
                                <th scope="col">Owner</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($expiring as $reference)
                                <tr>
                                    <th scope="row" class="cell-heading">
                                        <a href="{{ route('admin.security.secrets.edit', $reference) }}">{{ $reference->name }}</a>
                                        <span class="cell-note">{{ $reference->reference_type->label() }} - {{ $reference->provider->label() }}</span>
                                    </th>
                                    <td><span class="{{ $reference->status()->badgeClass() }}">{{ $reference->status()->label() }}</span></td>
                                    <td>{{ $reference->expires_on?->toFormattedDateString() ?? '-' }}</td>
                                    <td>{{ $reference->owner?->name ?? 'Nobody assigned' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($rotationDue->isNotEmpty())
            <section class="card" aria-labelledby="rotation-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="rotation-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-refresh"/></svg>
                        Rotation due
                        <span class="badge badge-warning">{{ $rotationDue->count() }}</span>
                    </h2>
                </div>
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Credentials whose rotation date has arrived</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">Reference</th>
                                <th scope="col">Rotation due</th>
                                <th scope="col">Owner</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rotationDue as $reference)
                                <tr>
                                    <th scope="row" class="cell-heading">
                                        <a href="{{ route('admin.security.secrets.edit', $reference) }}">{{ $reference->name }}</a>
                                    </th>
                                    <td>{{ $reference->rotation_due_on?->toFormattedDateString() ?? '-' }}</td>
                                    <td>{{ $reference->owner?->name ?? 'Nobody assigned' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="card" aria-labelledby="warnings-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="warnings-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                    Security warnings
                    @if ($warnings !== [])
                        <span class="badge badge-warning">{{ count($warnings) }}</span>
                    @endif
                </h2>
            </div>

            @if ($warnings === [])
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                    <span class="empty-title">Nothing flagged</span>
                    <span class="empty-note">
                        No policy on the four screens is weaker than its default, and every control that
                        could be checked passed.
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Findings across the four security areas</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">Area</th>
                                <th scope="col">Finding</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($warnings as $warning)
                                <tr>
                                    <th scope="row" class="cell-heading">{{ $warning['area'] }}</th>
                                    <td>{{ $warning['message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card" aria-labelledby="events-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="events-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-file-text"/></svg>
                    Recent security events
                </h2>
            </div>

            @if ($events->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-file-text"/></svg>
                    <span class="empty-title">No security events recorded yet</span>
                    <span class="empty-note">
                        Sign-ins, refused sign-ins, policy changes and session revocations appear here as
                        they happen.
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">The most recent security audit events</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">When</th>
                                <th scope="col">What</th>
                                <th scope="col">Outcome</th>
                                <th scope="col">Who</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($events as $event)
                                <tr>
                                    <th scope="row" class="cell-heading">{{ $event->occurred_at?->diffForHumans() }}</th>
                                    <td>
                                        {{ $event->action }}
                                        @if ($event->reason)
                                            <span class="cell-note">{{ $event->reason }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $event->outcome->label() }}</td>
                                    <td>{{ $event->actor_label ?? 'System' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

    </div>
@endsection
