<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Modules\Security\Enums\AuthenticationMode;
use App\Modules\Security\Enums\ConcurrentSessionPolicy;
use App\Modules\Security\Enums\CriticalAction;
use App\Modules\Security\Enums\PolicyValueType;

/*
|-------------------------------------------------------------------------------
| Security policy catalogue
|-------------------------------------------------------------------------------
|
| Features ADM-009 Authentication Policy, ADM-010 Session Policy and ADM-011 API
| Security. Decision D1, approved 25 August 2026.
|
| The same contract shape as `config/platform.php`: this file decides which keys
| exist, what type each is, what a valid value looks like, who may change it and
| whether a change needs a stated reason. `security_policies` holds OVERRIDES
| ONLY, so a fresh install needs no seeder and a key with no row reads as the
| default below.
|
| WHY THIS IS A SEPARATE CATALOGUE FROM config/platform.php
|
| `SystemSettings::set()` refuses any key that `Redaction::isSensitiveKey()`
| matches, and that fragment list contains "auth", "session", "key" and
| "secret". Every natural key for these three features trips it. Worse, the same
| list drives `Redaction::summarise()`, which writes the audit before/after
| summary - so a policy change stored under such a key would be recorded as
| "[redacted]" instead of "120 -> 30", degrading the trail for precisely the
| settings an auditor comes looking for.
|
| WHY THE KEY PREFIXES READ AS THEY DO
|
| Follows from the above. The prefixes are `sign_in.`, `activity.` and `api.`
| rather than `auth.` and `session.`, because the last two would be redacted out
| of their own audit trail. Each key was checked against the fragment list. If
| you add a key here, check it too: run it through
| `Redaction::isSensitiveKey()` and make sure the answer is false, or the change
| to it will be unreadable in the audit log. SEC-DEC-044.
|
| NOTHING HERE MAY HOLD A SECRET. These are policy switches, thresholds and
| allow-lists. A credential belongs in the server environment, pointed at by an
| ADM-012 secret reference. `SecurityPolicies::set()` refuses a secret-bearing
| key and a credential-shaped value, so the rule is enforced and not just
| stated.
|
| Field meanings:
|
| screen ........... which screen it appears on: authentication, sessions or api
| type ............. how the stored string is cast back, PolicyValueType
| default .......... the value in force when no override exists
| label/help ....... what the administrator reads
| rules ............ Laravel validation applied before an override is stored
| choices .......... allowed values, for PolicyValueType::Choice
| editable ......... the minimum tier that may change it
| high_risk ........ whether a change REQUIRES a written reason. Rule 4
| weakens_when ..... the value at which this key makes the system weaker, used
|                    by the overview to raise a warning. Null means neither
|                    value is inherently weaker
| requires ......... a named capability the value needs in order to do anything,
|                    checked at runtime. Today the only one is
|                    "session_enumeration", which the file driver cannot provide
|
*/

return [

    /*
    | Which screen each policy group belongs to, and what the screen is called.
    | A closed list: a group that is not named here cannot become a URL.
    */
    'screens' => [
        'authentication' => [
            'title' => 'Authentication Policy',
            'subtitle' => 'How people prove who they are before SemantIQ lets them in.',
            'feature' => 'ADM-009',
            'prefix' => 'sign_in.',
        ],
        'sessions' => [
            'title' => 'Session Policy',
            'subtitle' => 'How long a signed-in session lasts, and what it takes to prove yourself again.',
            'feature' => 'ADM-010',
            'prefix' => 'activity.',
        ],
        'api' => [
            'title' => 'API Security',
            'subtitle' => 'The controls this application applies to every request it serves.',
            'feature' => 'ADM-011',
            'prefix' => 'api.',
        ],
    ],

    /*
    | The critical actions that may demand re-authentication. ADM-010.
    |
    | Four rather than the six ADM-010 lists, because two of the six guard
    | actions that do not exist yet. See CriticalAction for why declaring the
    | other two now would be a control that silently protects nothing.
    */
    'critical_actions' => [
        CriticalAction::TierChange,
        CriticalAction::SystemAdministratorChange,
        CriticalAction::SecurityPolicyChange,
        CriticalAction::SecretReferenceChange,
    ],

    'policies' => [

        /* ---- ADM-009 Authentication Policy -------------------------------- */

        'sign_in.mode' => [
            'screen' => 'authentication',
            'type' => PolicyValueType::Choice,
            'default' => AuthenticationMode::FederatedWithLocalAdmin->value,
            'label' => 'Authentication mode',
            'help' => 'Which ways in are offered at all. The sign-in screen and both sign-in paths read this, so turning one off removes it rather than hiding it.',
            'choices' => [
                AuthenticationMode::FederatedOnly->value => AuthenticationMode::FederatedOnly->label(),
                AuthenticationMode::FederatedWithLocalAdmin->value => AuthenticationMode::FederatedWithLocalAdmin->label(),
                AuthenticationMode::LocalOnly->value => AuthenticationMode::LocalOnly->label(),
            ],
            'rules' => ['required', 'string'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => AuthenticationMode::LocalOnly->value,
        ],

        'sign_in.allow_local_admin' => [
            'screen' => 'authentication',
            'type' => PolicyValueType::Boolean,
            'default' => true,
            'label' => 'Allow break-glass local administrator sign-in',
            'help' => 'Keeps the credential form working for accounts marked as local administrators, so an Entra outage does not lock every administrator out. Turning it off is safer and riskier at the same time; make sure somebody else can restore access first.',
            'rules' => ['required', 'boolean'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => true,
        ],

        'sign_in.require_federated_for_business_users' => [
            'screen' => 'authentication',
            'type' => PolicyValueType::Boolean,
            'default' => true,
            'label' => 'Require Microsoft Entra for business users',
            'help' => 'Everybody who is not a local administrator must come through Entra. This is what makes the directory the authority on who works here.',
            'rules' => ['required', 'boolean'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => false,
        ],

        'sign_in.auto_create_users' => [
            'screen' => 'authentication',
            'type' => PolicyValueType::Boolean,
            'default' => false,
            'label' => 'Create an account on first Microsoft sign-in',
            'help' => 'When off, somebody who authenticates with Entra but has no SemantIQ account is refused rather than given one. Off is the safer default: it means an administrator decides who gets an account, not the directory.',
            'rules' => ['required', 'boolean'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => true,
        ],

        'sign_in.allowed_tenant_id' => [
            'screen' => 'authentication',
            'type' => PolicyValueType::Text,
            'default' => '',
            'label' => 'Allowed Microsoft tenant',
            'help' => 'The directory whose people may sign in, as a tenant GUID. Leave blank to accept whichever tenant the application registration is configured for. ADM-009 requires the tenant to be validated, and a blank value means the check falls back to the registration rather than being skipped.',
            'rules' => ['nullable', 'uuid'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => null,
        ],

        'sign_in.allowed_email_domains' => [
            'screen' => 'authentication',
            'type' => PolicyValueType::TextList,
            'default' => '',
            'label' => 'Allowed email domains',
            'help' => 'One domain per line, for example contoso.com. Leave blank to allow any domain the tenant contains. A guest account in the customer tenant carries its home domain, so this is what keeps an invited outsider out.',
            'rules' => ['nullable', 'string', 'max:2000'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => '',
        ],

        'sign_in.failed_attempt_threshold' => [
            'screen' => 'authentication',
            'type' => PolicyValueType::Integer,
            'default' => 5,
            'label' => 'Failed sign-in attempts before lockout',
            'help' => 'Counted per email address and network address together, not per account alone, so one attacker cannot lock a real person out by guessing at their address.',
            'rules' => ['required', 'integer', 'min:3', 'max:50'],
            'editable' => Role::SystemAdmin,
            'high_risk' => false,
            'weakens_when' => null,
        ],

        'sign_in.lock_minutes' => [
            'screen' => 'authentication',
            'type' => PolicyValueType::Integer,
            'default' => 1,
            'label' => 'Lockout duration, in minutes',
            'help' => 'How long the attempt counter holds after the threshold is reached.',
            'rules' => ['required', 'integer', 'min:1', 'max:1440'],
            'editable' => Role::SystemAdmin,
            'high_risk' => false,
            'weakens_when' => null,
        ],

        /* ---- ADM-010 Session Policy --------------------------------------- */

        'activity.idle_minutes' => [
            'screen' => 'sessions',
            'type' => PolicyValueType::Integer,
            'default' => 120,
            'label' => 'Idle timeout, in minutes',
            'help' => 'A session with no activity for this long is ended. Enforced on every request by EnforceSessionPolicy, not only by the cookie lifetime, so a stale session cannot outlive the policy.',
            'rules' => ['required', 'integer', 'min:5', 'max:1440'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => null,
        ],

        'activity.maximum_minutes' => [
            'screen' => 'sessions',
            'type' => PolicyValueType::Integer,
            'default' => 720,
            'label' => 'Maximum session duration, in minutes',
            'help' => 'A session is ended this long after sign-in however active it has been. Idle timeout stops an abandoned session; this stops one that never goes idle.',
            'rules' => ['required', 'integer', 'min:15', 'max:10080'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => null,
        ],

        'activity.remember_me_days' => [
            'screen' => 'sessions',
            'type' => PolicyValueType::Integer,
            'default' => 0,
            'label' => 'Remember me, in days',
            'help' => 'Zero turns it off, which is the default: a remembered sign-in survives the browser closing and is a credential sitting on a device. Any value above zero also survives the maximum session duration above, which is the point and the risk.',
            'rules' => ['required', 'integer', 'min:0', 'max:90'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => null,
        ],

        'activity.concurrent_policy' => [
            'screen' => 'sessions',
            'type' => PolicyValueType::Choice,
            'default' => ConcurrentSessionPolicy::Unlimited->value,
            'label' => 'Concurrent sessions',
            'help' => 'How many devices one person may be signed in on. Anything but unlimited needs SemantIQ to be able to list a person\'s live sessions, which the file session driver cannot do.',
            'choices' => [
                ConcurrentSessionPolicy::Unlimited->value => ConcurrentSessionPolicy::Unlimited->label(),
                ConcurrentSessionPolicy::Single->value => ConcurrentSessionPolicy::Single->label(),
                ConcurrentSessionPolicy::Limited->value => ConcurrentSessionPolicy::Limited->label(),
            ],
            'rules' => ['required', 'string'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => null,
            'requires' => 'session_enumeration',
        ],

        'activity.concurrent_limit' => [
            'screen' => 'sessions',
            'type' => PolicyValueType::Integer,
            'default' => 3,
            'label' => 'Maximum concurrent sessions',
            'help' => 'Used only when the policy above is set to a maximum. The oldest session ends when the limit is exceeded.',
            'rules' => ['required', 'integer', 'min:1', 'max:20'],
            'editable' => Role::SystemAdmin,
            'high_risk' => false,
            'weakens_when' => null,
            'requires' => 'session_enumeration',
        ],

        'activity.revocation_enabled' => [
            'screen' => 'sessions',
            'type' => PolicyValueType::Boolean,
            'default' => true,
            'label' => 'Allow an administrator to end somebody else\'s sessions',
            'help' => 'The control you need on the day an account is compromised. Needs the database session driver; the screen says so plainly when it is not available rather than offering a button that does nothing.',
            'rules' => ['required', 'boolean'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => false,
            'requires' => 'session_enumeration',
        ],

        'activity.confirm_critical_actions' => [
            'screen' => 'sessions',
            'type' => PolicyValueType::Boolean,
            'default' => true,
            'label' => 'Prove who you are again before a critical action',
            'help' => 'Applies to the four actions listed below this field. A credential account is asked for its password; a Microsoft account is sent back to Entra for a fresh sign-in. No extra Microsoft token is stored to do this.',
            'rules' => ['required', 'boolean'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => false,
        ],

        'activity.confirmation_valid_minutes' => [
            'screen' => 'sessions',
            'type' => PolicyValueType::Integer,
            'default' => 15,
            'label' => 'A confirmation stays good for, in minutes',
            'help' => 'How long one re-authentication covers further critical actions before another is asked for.',
            'rules' => ['required', 'integer', 'min:1', 'max:240'],
            'editable' => Role::SystemAdmin,
            'high_risk' => false,
            'weakens_when' => null,
        ],

        /* ---- ADM-011 API Security ----------------------------------------- */

        'api.security_headers' => [
            'screen' => 'api',
            'type' => PolicyValueType::Boolean,
            'default' => true,
            'label' => 'Send security response headers',
            'help' => 'Adds the content-type, framing, referrer and permissions headers to every response. On by default; the switch exists so a header that breaks an embedded view can be turned off deliberately rather than by editing code.',
            'rules' => ['required', 'boolean'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => false,
        ],

        'api.content_policy_mode' => [
            'screen' => 'api',
            'type' => PolicyValueType::Choice,
            'default' => 'report_only',
            'label' => 'Content Security Policy',
            'help' => 'Report-only is the default deliberately. An enforcing policy that is slightly wrong breaks the shell for everybody at once, so the safe order is to run it in report-only, read what it would have blocked, and then enforce.',
            'choices' => [
                'off' => 'Off',
                'report_only' => 'Report only',
                'enforce' => 'Enforce',
            ],
            'rules' => ['required', 'string'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => 'off',
        ],

        'api.hsts_enabled' => [
            'screen' => 'api',
            'type' => PolicyValueType::Boolean,
            /*
             * OFF, and it must stay off until separately approved. Rule 8.
             *
             * HSTS is the one header on this screen that cannot be taken back.
             * A browser that has seen it refuses plain HTTP to this host for
             * the whole max-age, whatever the server later sends, so a wrong
             * value is not a setting somebody can simply switch off again.
             */
            'default' => false,
            'label' => 'Send Strict-Transport-Security',
            'help' => 'Tells browsers never to reach this site over plain HTTP again. It CANNOT BE WITHDRAWN from a browser that has already seen it until the max-age below expires. Off until separately approved for production.',
            'rules' => ['required', 'boolean'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => null,
        ],

        'api.hsts_max_age_days' => [
            'screen' => 'api',
            'type' => PolicyValueType::Integer,
            /* One day, not the year the specification suggests. A short max-age
             * is the mistake you can recover from within a day. */
            'default' => 1,
            'label' => 'Strict-Transport-Security duration, in days',
            'help' => 'Start small. This is how long a browser will refuse plain HTTP after seeing the header, and a large value turns a certificate problem into an outage nobody can shorten.',
            'rules' => ['required', 'integer', 'min:1', 'max:730'],
            'editable' => Role::SystemAdmin,
            'high_risk' => true,
            'weakens_when' => null,
        ],

        'api.max_payload_kilobytes' => [
            'screen' => 'api',
            'type' => PolicyValueType::Integer,
            'default' => 2048,
            'label' => 'Maximum request size, in kilobytes',
            'help' => 'A request larger than this is refused before it is parsed. The web server has its own limit as well; this one is the application saying what it expects, and it is the one an administrator can see.',
            'rules' => ['required', 'integer', 'min:64', 'max:65536'],
            'editable' => Role::SystemAdmin,
            'high_risk' => false,
            'weakens_when' => null,
        ],

        'api.sensitive_rate_limit_per_minute' => [
            'screen' => 'api',
            'type' => PolicyValueType::Integer,
            'default' => 30,
            'label' => 'Rate limit for sensitive endpoints, per minute',
            'help' => 'Applies to the write routes on the security and identity screens. The sign-in form has its own, stricter limit on the Authentication Policy screen.',
            'rules' => ['required', 'integer', 'min:5', 'max:600'],
            'editable' => Role::SystemAdmin,
            'high_risk' => false,
            'weakens_when' => null,
        ],
    ],
];
