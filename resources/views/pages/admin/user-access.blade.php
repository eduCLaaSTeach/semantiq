{{--
    ADM-005, one account and everything about its access.

    THE MOST IMPORTANT LAYOUT DECISION IN THE RELEASE, and it is a layout
    decision as much as a code one: the two dimensions of the access model are
    two separate panels, with two separate forms, posting to two separate routes
    and producing two separate audit events.

        WHAT THEY MAY DO ....... the platform role, and additional roles
        WHAT THEY MAY READ ..... business domain entitlements

    A single "access" form setting both would make "made them an administrator"
    and "gave them Finance" one decision. ROLE_MODEL.md section 1 is that they
    are never one decision, and a screen that merges them teaches every
    administrator the opposite of the model the backend enforces.

    The panels say so in words too, because the separation is not obvious from
    the outside and an administrator who does not know it will grant a System
    Administrator role expecting it to include Finance.
--}}
@extends('layouts.shell')

@section('title', $user->name.' · '.config('app.name'))
@section('page-title', $user->name)
@section('page-subtitle', $user->email)

@section('page-action')
    <a class="btn btn-secondary" href="{{ route('admin.users.edit', $user) }}">
        <svg class="icon" aria-hidden="true"><use href="#i-sliders"/></svg>
        <span class="btn-label">Edit profile</span>
    </a>
@endsection

@section('content')
    <div class="stack">
        @include('partials.form-status')

        @unless ($mayChangeAuthority)
            {{-- Shown before any control that the invariant would refuse, so the
                 administrator reads the reason before they try. --}}
            <div class="alert alert-info" role="note">
                <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
                <span>
                    This is the last active System Administrator. Their role cannot be lowered
                    and their account cannot be disabled, because that would leave nobody able
                    to administer SemantIQ - including nobody able to undo it.
                </span>
            </div>
        @endunless

        <div class="detail-grid">
            <div class="stack">
                {{-- The record itself. --}}
                <section class="card" aria-labelledby="profile-heading">
                    <div class="panel-head card-head">
                        <h2 class="panel-title" id="profile-heading">
                            <svg class="icon" aria-hidden="true"><use href="#i-user-round"/></svg>
                            Account
                        </h2>
                        <span class="{{ $user->status->badgeClass() }}">{{ $user->status->label() }}</span>
                    </div>
                    <div class="record-list">
                        <div class="record-row">
                            <span class="record-label">Type</span>
                            <span class="record-value">
                                <span class="{{ $user->user_type->badgeClass() }}">{{ $user->user_type->label() }}</span>
                            </span>
                        </div>
                        <div class="record-row">
                            <span class="record-label">Signs in with</span>
                            <span class="record-value">{{ $user->authentication_source === 'entra' ? 'Microsoft Entra' : 'A password held here' }}</span>
                        </div>
                        <div class="record-row">
                            <span class="record-label">Placement</span>
                            <span class="record-value">
                                {{ $user->businessUnit?->name ?? 'No business unit' }}
                                @if ($user->team) &middot; {{ $user->team->name }} @endif
                            </span>
                        </div>
                        <div class="record-row">
                            <span class="record-label">Access window</span>
                            <span class="record-value">
                                @if ($user->access_start === null && $user->access_end === null)
                                    No limit
                                @else
                                    {{ $user->access_start?->toFormattedDateString() ?? 'Any time' }}
                                    to
                                    {{ $user->access_end?->toFormattedDateString() ?? 'no end date' }}
                                @endif
                            </span>
                        </div>
                        <div class="record-row">
                            <span class="record-label">Last signed in</span>
                            <span class="record-value">{{ $user->last_signed_in_at?->diffForHumans() ?? 'Never' }}</span>
                        </div>
                    </div>
                </section>

                {{-- Status. Its own form and its own audit event. --}}
                <section class="card" aria-labelledby="status-heading">
                    <div class="panel-head card-head">
                        <h2 class="panel-title" id="status-heading">
                            <svg class="icon" aria-hidden="true"><use href="#i-toggle"/></svg>
                            Account status
                        </h2>
                    </div>
                    <form class="inline-form" method="POST" action="{{ route('admin.users.status', $user) }}">
                        @csrf
                        <div class="field">
                            <label class="field-label" for="status">Set status to</label>
                            <select class="input" id="status" name="status">
                                @foreach ($statuses as $option)
                                    <option value="{{ $option->value }}" @selected($user->status === $option)>{{ $option->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="status-reason">Reason</label>
                            <input class="input" type="text" id="status-reason" name="reason" maxlength="200" placeholder="Optional">
                        </div>
                        <button type="submit" class="btn btn-secondary" data-async>
                            <span class="btn-label">Change status</span>
                        </button>
                    </form>
                    <p class="field-help" style="padding:0 var(--space-3) var(--space-3)">
                        Only an active account can sign in. Disabling somebody takes effect
                        immediately, including on a session they already have open.
                    </p>
                </section>
            </div>

            <div class="stack">
                {{-- DIMENSION ONE: what they may do. --}}
                <section class="card" aria-labelledby="authority-heading">
                    <div class="panel-head card-head">
                        <h2 class="panel-title" id="authority-heading">
                            <svg class="icon" aria-hidden="true"><use href="#i-key"/></svg>
                            What they may do
                        </h2>
                        <span class="badge">{{ $user->role->label() }}</span>
                    </div>

                    <p class="field-help" style="padding:0 var(--space-3)">
                        Their authority over the platform. This grants no business information
                        of any kind.
                    </p>

                    <form class="inline-form" method="POST" action="{{ route('admin.users.tier', $user) }}">
                        @csrf
                        <div class="field">
                            <label class="field-label" for="role">Role</label>
                            <select class="input" id="role" name="role" @unless($mayChangeAuthority) disabled @endunless>
                                @foreach ($tiers as $tier)
                                    <option value="{{ $tier->value }}" @selected($user->role === $tier)>{{ $tier->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="role-reason">Reason</label>
                            <input class="input" type="text" id="role-reason" name="reason" maxlength="200" placeholder="Optional">
                        </div>
                        <button type="submit" class="btn btn-secondary" data-async @unless($mayChangeAuthority) disabled @endunless>
                            <span class="btn-label">Change role</span>
                        </button>
                    </form>

                    <div class="panel-head card-head">
                        <h3 class="panel-title" style="font-size:var(--text-body)">Additional roles</h3>
                    </div>

                    @if ($user->accessRoles->isEmpty())
                        <p class="field-help" style="padding:0 var(--space-3) var(--space-3)">
                            None. Their role above already carries its own permissions.
                        </p>
                    @else
                        <div class="table-scroll">
                            <table class="data-table">
                                <caption class="visually-hidden">Additional roles held</caption>
                                <thead>
                                    <tr>
                                        <th scope="col" class="col-primary">Role</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($user->accessRoles as $held)
                                        <tr>
                                            <th scope="row" class="cell-heading">
                                                {{ $held->name }}
                                                <span class="cell-note cell-reference">{{ $held->code }}</span>
                                            </th>
                                            <td>
                                                <form method="POST" action="{{ route('admin.users.roles', $user) }}">
                                                    @csrf
                                                    <input type="hidden" name="role_id" value="{{ $held->id }}">
                                                    <input type="hidden" name="operation" value="remove">
                                                    <button type="submit" class="btn btn-secondary btn-small is-danger" data-async>
                                                        <svg class="icon" aria-hidden="true"><use href="#i-user-minus"/></svg>
                                                        <span class="btn-label">Remove</span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($assignableRoles->isNotEmpty())
                        <form class="inline-form" method="POST" action="{{ route('admin.users.roles', $user) }}">
                            @csrf
                            <input type="hidden" name="operation" value="assign">
                            <div class="field">
                                <label class="field-label" for="role_id">Add a role</label>
                                <select class="input" id="role_id" name="role_id">
                                    @foreach ($assignableRoles as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-secondary" data-async>
                                <span class="btn-label">Add role</span>
                            </button>
                        </form>
                    @endif
                </section>

                {{-- DIMENSION TWO: what they may read. --}}
                <section class="card" aria-labelledby="entitlements-heading">
                    <div class="panel-head card-head">
                        <h2 class="panel-title" id="entitlements-heading">
                            <svg class="icon" aria-hidden="true"><use href="#i-ticket"/></svg>
                            What they may read
                        </h2>
                        <span class="badge">{{ count($entitled) }} of {{ count($domains) }}</span>
                    </div>

                    <p class="field-help" style="padding:0 var(--space-3)">
                        Business information, granted one domain at a time. Their platform role
                        above has no effect on this list -
                        @if ($user->role === \App\Enums\Role::SystemAdmin)
                            a System Administrator with no domains here can operate the platform
                            and read none of your business data.
                        @else
                            a higher role does not add to it.
                        @endif
                    </p>

                    <div class="table-scroll">
                        <table class="data-table">
                            <caption class="visually-hidden">Business domain access</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="col-primary">Business domain</th>
                                    <th scope="col">Access</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($domains as $domain)
                                    @php($has = in_array($domain->value, $entitled, true))
                                    <tr>
                                        <th scope="row" class="cell-heading">
                                            {{ $domain->label() }}
                                            @if ($domain->isSensitive())
                                                <span class="cell-note">Carries restricted fields</span>
                                            @endif
                                        </th>
                                        <td>
                                            <span class="badge {{ $has ? 'badge-success' : '' }}">
                                                {{ $has ? 'Granted' : 'No access' }}
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.users.entitlements', $user) }}" class="row-form">
                                                @csrf
                                                <input type="hidden" name="domain" value="{{ $domain->value }}">
                                                <input type="hidden" name="operation" value="{{ $has ? 'revoke' : 'grant' }}">
                                                <label class="field-label visually-hidden" for="ent-reason-{{ $domain->value }}">
                                                    Reason for changing {{ $domain->label() }} access
                                                </label>
                                                <input class="input" type="text" id="ent-reason-{{ $domain->value }}"
                                                       name="reason" maxlength="200" placeholder="Reason (optional)">
                                                <button type="submit" class="btn btn-secondary btn-small @if($has) is-danger @endif" data-async>
                                                    <svg class="icon" aria-hidden="true"><use href="#{{ $has ? 'i-user-minus' : 'i-user-plus' }}"/></svg>
                                                    <span class="btn-label">{{ $has ? 'Revoke' : 'Grant' }}</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- ADM-007: effective permissions must be determinable for a
                     user. This is that answer - the union of tier defaults and
                     assigned roles, already filtered by the tier ceiling, so it
                     is what they can actually do rather than what was ticked. --}}
                <section class="card" aria-labelledby="effective-heading">
                    <div class="panel-head card-head">
                        <h2 class="panel-title" id="effective-heading">
                            <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
                            Effective permissions
                        </h2>
                        <span class="badge">{{ count($effective) }}</span>
                    </div>

                    @if ($effective === [])
                        <div class="empty">
                            <svg class="icon" aria-hidden="true"><use href="#i-slash"/></svg>
                            <span class="empty-title">None</span>
                            <span class="empty-note">
                                @if (! $user->status->permitsAuthentication())
                                    This account is {{ strtolower($user->status->label()) }}, so it holds no permissions at all.
                                @else
                                    This role carries no administrative permissions.
                                @endif
                            </span>
                        </div>
                    @else
                        <div class="pill-row" style="padding:0 var(--space-3) var(--space-3)">
                            @foreach ($effective as $key)
                                <span class="badge cell-reference">{{ $key }}</span>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>
@endsection
