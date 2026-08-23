<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Modules\Platform\Enums\SettingType;

/*
|-------------------------------------------------------------------------------
| Platform configuration catalogue
|-------------------------------------------------------------------------------
|
| Feature ADM-021. Release 1 asks that each configuration setting carry a key,
| category, typed value, default, validation, scope, editable role, audit
| requirement, approval requirement and sensitive flag.
|
| All of that except the VALUE lives here, in code, rather than in the database.
| The catalogue is a contract: it decides which keys exist, what type each is,
| who may change it and what a valid value looks like. A catalogue an
| administrator could edit would not be a contract, and an unknown key that
| resolved to something would be a way to introduce configuration nobody
| reviewed.
|
| `system_settings` and `feature_flags` therefore hold OVERRIDES ONLY. A key
| with no row reads as the default below, so a fresh install needs no seeder.
|
| NOTHING HERE MAY HOLD A SECRET. Credentials belong in the server environment
| or, from gate 3, behind a `secret_references` pointer. The writer refuses any
| key whose name reads as secret-bearing, so this rule is enforced and not just
| stated.
|
*/

return [

    /*
    | The organisation code created by the bootstrap migration. Recorded here so
    | code that needs to name the founding organisation does not hard-code the
    | string in several places. ADM-002 edits that row; it never creates another.
    */
    'bootstrap_organisation_code' => 'PRIMARY',

    /*
    | Settings.
    |
    | category ......... which screen it appears on: general or environment
    | type ............. how the stored string is cast back, SettingType
    | default .......... the value when no override exists
    | label/help ....... what the administrator reads
    | rules ............ Laravel validation applied before an override is stored
    | choices .......... allowed values, for SettingType::Choice
    | editable ......... the minimum tier that may change it
    | audited .......... whether a change writes an audit event
    | approval ......... whether a change needs a second person to approve it.
    |                    False everywhere in Release 1: the approval workflow
    |                    arrives with gate 4, and a flag claiming an approval
    |                    that nothing enforces would be worse than none.
    | sensitive ........ whether the VALUE is masked on screen. No setting is
    |                    sensitive today; the column exists so that a future one
    |                    cannot be added without deciding.
    */
    'settings' => [

        'app.display_name' => [
            'category' => 'general',
            'type' => SettingType::Text,
            'default' => 'SemantIQ',
            'label' => 'Application display name',
            'help' => 'Shown in the browser tab, the sign-in screen and notifications.',
            'rules' => ['required', 'string', 'max:80'],
            'editable' => Role::SystemAdmin,
            'audited' => true,
            'approval' => false,
            'sensitive' => false,
        ],

        'app.support_contact' => [
            'category' => 'general',
            'type' => SettingType::Text,
            'default' => '',
            'label' => 'Support contact',
            'help' => 'The address a business user is given when something fails. Shown to signed-in people, so use a monitored team address rather than a personal one.',
            'rules' => ['nullable', 'email:rfc', 'max:190'],
            'editable' => Role::SystemAdmin,
            'audited' => true,
            'approval' => false,
            'sensitive' => false,
        ],

        'app.default_locale' => [
            'category' => 'general',
            'type' => SettingType::Choice,
            'default' => 'en',
            'label' => 'Default language',
            'help' => 'Used for people who have not chosen one.',
            'choices' => ['en' => 'English'],
            'editable' => Role::SystemAdmin,
            'audited' => true,
            'approval' => false,
            'sensitive' => false,
        ],

        'app.default_time_zone' => [
            'category' => 'general',
            'type' => SettingType::Text,
            'default' => 'UTC',
            'label' => 'Default time zone',
            'help' => 'How dates are shown to people who have not chosen one. Stored data stays in UTC.',
            /* `timezone` rather than a 400-entry choice list: the identifier set
             * belongs to PHP and would drift the moment it were copied. */
            'rules' => ['required', 'timezone'],
            'editable' => Role::SystemAdmin,
            'audited' => true,
            'approval' => false,
            'sensitive' => false,
        ],

        'app.pagination_default' => [
            'category' => 'general',
            'type' => SettingType::Integer,
            'default' => 25,
            'label' => 'Rows per page',
            'help' => 'The default page size for administrative lists.',
            /* Bounded at both ends. An unbounded page size is a cheap way to
             * ask the database for an entire table. */
            'rules' => ['required', 'integer', 'min:10', 'max:200'],
            'editable' => Role::SystemAdmin,
            'audited' => true,
            'approval' => false,
            'sensitive' => false,
        ],

        'notifications.default_channel' => [
            'category' => 'general',
            'type' => SettingType::Choice,
            'default' => 'none',
            'label' => 'Default notification channel',
            'help' => 'How SemantIQ reaches an administrator when something needs attention. Email requires a configured mail transport.',
            'choices' => ['none' => 'None', 'email' => 'Email'],
            'editable' => Role::SystemAdmin,
            'audited' => true,
            'approval' => false,
            'sensitive' => false,
        ],

        'environment.label' => [
            'category' => 'environment',
            'type' => SettingType::Text,
            'default' => '',
            'label' => 'Environment label',
            'help' => 'Shown in the shell so a test instance is never mistaken for the live one. Leave empty on production.',
            'rules' => ['nullable', 'string', 'max:24'],
            'editable' => Role::SystemAdmin,
            'audited' => true,
            'approval' => false,
            'sensitive' => false,
        ],

        'environment.maintenance_mode' => [
            'category' => 'environment',
            'type' => SettingType::Boolean,
            'default' => false,
            'label' => 'Maintenance notice',
            'help' => 'Shows a maintenance notice to business users. It does not stop administrators working, and it is not a substitute for taking the application down.',
            'rules' => ['boolean'],
            'editable' => Role::SystemAdmin,
            'audited' => true,
            'approval' => false,
            'sensitive' => false,
        ],

        'environment.maintenance_message' => [
            'category' => 'environment',
            'type' => SettingType::Text,
            'default' => '',
            'label' => 'Maintenance notice text',
            'help' => 'What the notice says. Shown to every signed-in person, so it must contain no incident detail, no host names and no customer information.',
            'rules' => ['nullable', 'string', 'max:200'],
            'editable' => Role::SystemAdmin,
            'audited' => true,
            'approval' => false,
            'sensitive' => false,
        ],

    ],

    /*
    | Feature flags.
    |
    | A flag decides whether a capability is AVAILABLE. It never decides who may
    | use it: that stays with the tier, the permission and the domain
    | entitlement. Nothing here may be used to grant access.
    |
    | An unknown flag reads as OFF, so deleting a declaration turns a capability
    | off rather than silently on.
    |
    | `requires` names a precondition that must hold before the flag may be set
    | to the given value. It exists because some switches are safe in one
    | direction only.
    */
    'flags' => [

        'identity.local_sign_in' => [
            'label' => 'Local password sign-in',
            'help' => 'Offer the email and password form alongside Microsoft sign-in. Turn it off once every administrator signs in through Microsoft Entra, so this application holds no password worth attacking.',
            'default' => true,
            'editable' => Role::SystemAdmin,
            /* Turning it off with no working federated route would lock every
             * administrator out of the instance. */
            'requires' => ['off' => 'microsoft_sso_configured'],
        ],

        'platform.extended_diagnostics' => [
            'label' => 'Extended diagnostics',
            'help' => 'Adds runtime detail to the Diagnostics screen: driver names, loaded extensions and cache state. It exposes no credential and no customer data, but it does describe the host, so leave it off unless you are investigating something.',
            'default' => false,
            'editable' => Role::SystemAdmin,
            'requires' => [],
        ],

    ],

];
