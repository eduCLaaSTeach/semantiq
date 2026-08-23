{{--
    ADM-009 Authentication Policy, ADM-010 Session Policy and ADM-011 API Security.

    One template for three screens: they differ in which catalogue screen they
    render and in the CONTEXT around it, not in how a field is drawn. The fields
    are generated from config/security.php rather than written out here, because
    a hand-written field is a second copy of the catalogue and drifts from it.

    Page-hosted form, roomy sizing, one column - the template's forms section.
    Every field is a visible label, the control, its help text and a RESERVED
    validation slot, so an appearing error cannot shift the fields below it.

    THREE THINGS THIS SCREEN DOES THAT THE SYSTEM SETTINGS FORM DOES NOT:

    1. A REASON box. Rule 4 of the gate: a high-risk change needs one, and the
       service refuses without it. The box is always shown rather than appearing
       when a risky field changes, because a control that appears while you are
       typing is a control people fight.

    2. NOT-IN-FORCE FIELDS. A policy whose capability the environment cannot
       provide is drawn with its value and a plain sentence saying it is stored
       and not being applied. The value is honest; the claim that it is
       protecting something would not be.

    3. NO WORKING-LOOKING CONTROL FOR SOMETHING THAT CANNOT WORK. Rule 10. Where
       session revocation is unavailable the action is ABSENT, not disabled.

    No policy on this screen can hold a secret. SecurityPolicies::set() refuses a
    secret-bearing key and a credential-shaped value, so there is no masked field
    here and no path to one.
--}}
@extends('layouts.shell')

@section('title', $title.' · '.config('app.name'))
@section('page-title', $title)
@section('page-subtitle', $subtitle)

@section('content')
    <div class="stack">
        @include('partials.form-status')

        {{-- ADM-009 context: what is actually possible on this deployment. --}}
        @isset($entraConfigured)
            @unless ($entraConfigured)
                <div class="alert alert-warning" role="alert">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                    <span>
                        <strong>Microsoft Entra is not fully configured.</strong>
                        At least one value the sign-in flow needs is missing from the server environment.
                        Do not set the authentication mode to Entra-only until it is working, or nobody
                        will be able to sign in - including you.
                    </span>
                </div>
            @endunless

            <div class="alert alert-info" role="note">
                <svg class="icon" aria-hidden="true"><use href="#i-key"/></svg>
                <span>
                    <strong>{{ $localAdministrators }}</strong>
                    {{ $localAdministrators === 1 ? 'account' : 'accounts' }}
                    could still sign in with a password if Microsoft Entra stopped working.
                    @if ($localAdministrators === 0)
                        With none, turning break-glass sign-in off changes nothing today - and an Entra
                        outage would lock everybody out with no way back in.
                    @endif
                </span>
            </div>

            @if ($lockedOutLocalAccounts > 0)
                {{-- The number nobody thinks to ask for. Under the current mode
                     a local account below System Administrator cannot use the
                     password form at all - which is what "require Entra for
                     business users" MEANS, and is also what turns a policy
                     change into eleven people unable to sign in on Monday. --}}
                <div class="alert alert-warning" role="alert">
                    <svg class="icon" aria-hidden="true"><use href="#i-users"/></svg>
                    <span>
                        <strong>{{ $lockedOutLocalAccounts }}</strong>
                        {{ $lockedOutLocalAccounts === 1 ? 'account has' : 'accounts have' }}
                        a password here but cannot use the sign-in form under the current mode, because
                        {{ $lockedOutLocalAccounts === 1 ? 'it is' : 'they are' }}
                        not a local System Administrator. That is what requiring Microsoft Entra for
                        business users means - make sure
                        {{ $lockedOutLocalAccounts === 1 ? 'that person' : 'those people' }}
                        can sign in with Microsoft before relying on it.
                    </span>
                </div>
            @endif
        @endisset

        {{-- ADM-010 context: what this session driver can and cannot do. --}}
        @isset($sessionsAreEnumerable)
            @unless ($sessionsAreEnumerable)
                <div class="alert alert-warning" role="alert">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                    <span>
                        <strong>Some controls on this screen cannot be applied here.</strong>
                        {{ $sessionBlocker }}
                    </span>
                </div>
            @endunless
        @endisset

        {{-- ADM-011: the live control report. Above the form deliberately - it
             is the reason to be on this screen, and the switches below it are
             the smaller half. --}}
        @isset($controls)
            <section class="card" aria-labelledby="controls-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="controls-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-shield-alert"/></svg>
                        Controls in force
                        <span class="{{ $controlsOverall->badgeClass() }}">{{ $controlsOverall->label() }}</span>
                    </h2>
                </div>

                <div class="alert alert-info" role="note">
                    <svg class="icon" aria-hidden="true"><use href="#i-search-check"/></svg>
                    <span>
                        Each control is checked against the running application - the route table, the
                        middleware, the redactor - not against a stored value. A control that cannot be
                        established reads <strong>Not Verified</strong> and never Healthy.
                    </span>
                </div>

                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Security controls and their current state</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-label">Control</th>
                                <th scope="col">State</th>
                                <th scope="col">What was found</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($controls as $control)
                                <tr>
                                    <th scope="row" class="cell-heading">
                                        {{ $control['name'] }}
                                        <span class="cell-note">{{ $control['requirement'] }}</span>
                                    </th>
                                    <td><span class="{{ $control['status']->badgeClass() }}">{{ $control['status']->label() }}</span></td>
                                    <td>{{ $control['detail'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endisset

        <form class="card settings-form"
              method="POST"
              action="{{ route($updateRoute) }}"
              novalidate>
            @csrf
            @method('PUT')

            <div class="settings-fields">
                @foreach ($definitions as $key => $definition)
                    @php
                        /* Policy keys contain dots and Laravel reads a dot as
                           nesting, so the field name is slugged and mapped back
                           in UpdateSecurityPolicyRequest. */
                        $field = \App\Modules\Security\Http\Requests\UpdateSecurityPolicyRequest::slug($key);
                        $errorKey = 'policies.'.$field;
                        $current = old('policies.'.$field, $values[$key]);
                        $type = $definition['type'];
                        $blocker = $blockers[$key] ?? null;
                        $highRisk = ($definition['high_risk'] ?? false) === true;
                    @endphp

                    <div class="field">
                        @if ($type === \App\Modules\Security\Enums\PolicyValueType::Boolean)
                            {{-- A checkbox carries its own label, so it does not
                                 get a second one above it. --}}
                            <label class="checkbox" for="{{ $field }}">
                                <input type="checkbox"
                                       id="{{ $field }}"
                                       name="policies[{{ $field }}]"
                                       value="1"
                                       @checked((bool) $current)>
                                {{ $definition['label'] }}
                                @if ($highRisk)
                                    <span class="badge badge-warning">Needs a reason</span>
                                @endif
                            </label>
                        @else
                            <label class="field-label" for="{{ $field }}">
                                {{ $definition['label'] }}
                                @if (in_array('required', (array) ($definition['rules'] ?? []), true))
                                    <span class="field-required" aria-hidden="true">*</span>
                                @endif
                                @if ($highRisk)
                                    <span class="badge badge-warning">Needs a reason</span>
                                @endif
                            </label>

                            @if ($type === \App\Modules\Security\Enums\PolicyValueType::Choice)
                                <select class="input"
                                        id="{{ $field }}"
                                        name="policies[{{ $field }}]"
                                        @error($errorKey) aria-invalid="true" aria-describedby="{{ $field }}-message" @enderror>
                                    @foreach ($definition['choices'] as $value => $label)
                                        <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            @elseif ($type === \App\Modules\Security\Enums\PolicyValueType::TextList)
                                {{-- One entry per line. A textarea rather than a
                                     repeating row control: the list is short,
                                     is read as a whole, and pasting a list from
                                     a spreadsheet is the common case. --}}
                                <textarea class="input"
                                          id="{{ $field }}"
                                          name="policies[{{ $field }}]"
                                          rows="4"
                                          @error($errorKey) aria-invalid="true" aria-describedby="{{ $field }}-message" @enderror>{{ $current }}</textarea>
                            @else
                                <input class="input"
                                       type="{{ $type === \App\Modules\Security\Enums\PolicyValueType::Integer ? 'number' : 'text' }}"
                                       id="{{ $field }}"
                                       name="policies[{{ $field }}]"
                                       value="{{ $current }}"
                                       @error($errorKey) aria-invalid="true" aria-describedby="{{ $field }}-message" @enderror>
                            @endif
                        @endif

                        <p class="field-help">{{ $definition['help'] }}</p>

                        @if ($blocker !== null)
                            {{-- Stored, and not being applied. Said plainly
                                 rather than implied by a disabled control.

                                 SHORT, and the full explanation is in the
                                 banner at the top of the screen. Repeating the
                                 whole paragraph beside each affected field put
                                 the same four sentences on the page four times,
                                 which reads as nagging and trains people to
                                 skip it. Found in browser verification. --}}
                            <p class="field-help">
                                <span class="badge">Not Available</span>
                                Stored, but not being applied on this deployment. See the note at the top of this screen.
                            </p>
                        @endif

                        {{-- Reserved whether or not it holds a message. --}}
                        <p class="field-message" id="{{ $field }}-message">@error($errorKey){{ $message }}@enderror</p>
                    </div>
                @endforeach

                {{-- ADM-010: what "critical action" actually means here, listed
                     rather than left as a phrase in the help text. --}}
                @isset($criticalActions)
                    <div class="field">
                        <span class="field-label">Actions that ask for confirmation</span>
                        <ul class="field-help">
                            @foreach ($criticalActions as $action)
                                <li>{{ $action->label() }} - {{ $action->help() }}</li>
                            @endforeach
                        </ul>
                        <p class="field-help">
                            Not yet covered, because the actions do not exist yet:
                            {{ implode('; ', $deferredActions) }}. Each arrives with the gate that builds it.
                        </p>
                    </div>
                @endisset

                <div class="field">
                    {{-- No required mark. The asterisk means "always required"
                         everywhere else in this application, and this field is
                         required only when a value marked "Needs a reason"
                         actually changes. Marking it unconditionally would make
                         the asterisk mean something different here than on
                         every other screen. --}}
                    <label class="field-label" for="reason">Reason for this change</label>
                    <textarea class="input"
                              id="reason"
                              name="reason"
                              rows="3"
                              {{-- Short. The long form of this sentence wrapped
                                   to three lines at 390px and overflowed the
                                   box; the full explanation is in the help text
                                   underneath, where it has room. --}}
                              placeholder="Why, in a sentence."
                              @error('reason') aria-invalid="true" aria-describedby="reason-message" @enderror>{{ old('reason') }}</textarea>
                    <p class="field-help">
                        Required for any field marked <span class="badge badge-warning">Needs a reason</span>.
                        A security policy weakened without an explanation is one nobody dares change back.
                        The reason is stored with the value and in the audit trail.
                    </p>
                    <p class="field-message" id="reason-message">@error('reason'){{ $message }}@enderror</p>
                </div>
            </div>

            <div class="settings-foot">
                <button type="submit" class="btn btn-solid btn-primary" data-async>
                    <span class="btn-label">Save changes</span>
                </button>

                <span class="field-help">
                    Every change here is recorded in the audit trail with your name, the old value and the new one.
                </span>
            </div>

        </form>

        {{-- ADM-010: the viewer's own sessions. Only rendered where the driver
             can actually list them; where it cannot, nothing here pretends. --}}
        @isset($ownSessions)
            <section class="card" aria-labelledby="sessions-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="sessions-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-monitor"/></svg>
                        Your signed-in sessions
                    </h2>
                </div>

                @if (! $sessionsAreEnumerable)
                    <div class="empty">
                        <svg class="icon" aria-hidden="true"><use href="#i-slash"/></svg>
                        <span class="empty-title">Sessions cannot be listed on this deployment</span>
                        <span class="empty-note">{{ $sessionBlocker }}</span>
                    </div>
                @elseif ($ownSessions === [])
                    <div class="empty">
                        <svg class="icon" aria-hidden="true"><use href="#i-monitor"/></svg>
                        <span class="empty-title">No sessions recorded</span>
                        <span class="empty-note">
                            Your current session has not been written to the session store yet.
                        </span>
                    </div>
                @else
                    <div class="table-scroll">
                        <table class="data-table">
                            <caption class="visually-hidden">Your live sessions</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="col-label">Last active</th>
                                    <th scope="col">Network address</th>
                                    <th scope="col">Browser</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ownSessions as $session)
                                    <tr>
                                        <th scope="row" class="cell-heading">
                                            {{ $session['last_active_at']->diffForHumans() }}
                                            @if ($session['is_current'])
                                                <span class="badge badge-info">This one</span>
                                            @endif
                                        </th>
                                        <td>{{ $session['ip_address'] ?? '-' }}</td>
                                        <td>{{ $session['user_agent'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="field-help">
                        Ending another account's sessions is done from that account's page, where you can
                        see whose sessions you are ending. This list is deliberately only your own: a page
                        showing every live session in the organisation would be a targeting list.
                    </p>
                @endif

            </section>
        @endisset

    </div>
@endsection
