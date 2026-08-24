<?php

declare(strict_types=1);

use App\Modules\Governance\Enums\DataClassification;

/*
|-------------------------------------------------------------------------------
| Governance catalogue
|-------------------------------------------------------------------------------
|
| Features ADM-014 Data Protection Profile, ADM-015 Data Sovereignty Profile and
| the personal data category register. Release 1 gate 4, batch R1.4a.
|
| The same contract shape as `config/platform.php` and `config/security.php`:
| this file decides what a fresh install starts from and what the screens offer.
| The database holds what a customer has actually decided. A profile row that
| does not exist yet reads as the SAFE DEFAULTS below, never as a claim that
| somebody has decided anything.
|
| THE RULE THIS FILE IS WRITTEN UNDER, from the approved gate 4 decisions:
|
|   Engineering implements configurable fields and defaults. Engineering does
|   NOT invent retention periods or lawful bases. Where a value is a compliance
|   judgement, the default here is null and the screen says Not Configured.
|
| So: the PDPC notification default IS stated, because it was accepted for
| implementation. Its BASIS text is null, because that remains compliance-owned.
| Retention periods are absent entirely - they arrive with PDPA-03 in R1.4b, and
| declaring empty fields for them here would be an unwanted part hanging.
|
| THE AUDIT REDACTOR CONSTRAINT, carried from SEC-DEC-044.
|
| `Redaction::isSensitiveKey()` matches these fragments ANYWHERE in a name:
|
|     password passwd pwd secret token credential key authorization
|     authorisation auth cookie session private certificate cert signature
|     salt nonce connectionstring dsn clientassertion
|
| A key containing one is stored in the audit trail as "[redacted]" instead of
| its value, which degrades the trail for exactly the settings an auditor comes
| looking for. Three names this gate would naturally reach for are therefore
| BANNED and their replacements are used instead:
|
|     authorised_geographies / authorized_regions  ->  approved_geographies
|     certification_reference                      ->  evidence_reference
|     subject_key                                  ->  subject_reference
|
| If you add a key here, run it through `Redaction::isSensitiveKey()` first.
| `GovernanceCatalogueTest` asserts every key below survives it, so a mistake
| fails CI rather than surfacing months later in an unreadable audit row.
|
| NOTHING HERE MAY HOLD A SECRET. These are policy positions and reference
| lists. Contact fields hold a NAME or a ROLE and never a credential.
|
*/

return [

    /*
    | The privacy regime. SEC-DEC-041 determined the Singapore PDPA applies to
    | the current deployment; DEC-002 traced the obligations. The value is still
    | a field rather than a constant, because the product is meant to be sold to
    | customers in other jurisdictions and a hard-coded regime would have to be
    | found and unpicked on the first of them.
    |
    | `basis` is deliberately null. Engineering may state which regime was
    | determined; it may not write the legal reasoning for it.
    */
    'regime' => [
        'default' => 'Singapore PDPA',
        'choices' => [
            'Singapore PDPA' => 'Singapore Personal Data Protection Act',
            'EU GDPR' => 'EU General Data Protection Regulation',
            'UK GDPR' => 'UK General Data Protection Regulation',
            'Other' => 'Another regime, described in the notes',
            'Not determined' => 'Not yet determined',
        ],
        'basis_default' => null,
    ],

    /*
    | The PDPC notification deadline. Decision D7, approved 24 August 2026.
    |
    | Three calendar days is accepted FOR IMPLEMENTATION. The basis and
    | reference text remain compliance-owned, which is why `basis_default` is
    | null and the screen shows Not Configured until somebody fills it in.
    |
    | The resolved deadline is frozen onto a breach record when the notification
    | decision is made (R1.4c), so editing this afterwards cannot move a date
    | somebody is being held to.
    */
    'breach_notification' => [
        'due_days_default' => 3,
        'due_days_rules' => ['integer', 'min:1', 'max:90'],
        'basis_default' => null,
        'unit' => 'calendar days',
    ],

    /*
    | Geographies offered on the sovereignty profile.
    |
    | A short curated list rather than every country, for the same reason
    | ADM-002 curates its country list: a 249-entry select is a field nobody
    | sets correctly. "Not determined" is first and is the honest starting
    | point - it is not the same answer as any country, and the screen must not
    | let it read as one.
    */
    'geographies' => [
        'not_determined' => 'Not determined',
        'sg' => 'Singapore',
        'my' => 'Malaysia',
        'id' => 'Indonesia',
        'in' => 'India',
        'au' => 'Australia',
        'jp' => 'Japan',
        'gb' => 'United Kingdom',
        'eu' => 'European Union',
        'us' => 'United States',
        'other' => 'Elsewhere, described in the notes',
    ],

    /*
    | What the sovereignty profile starts from before anybody approves one.
    |
    | Decision D12, approved 24 August 2026: seed from the CONFIRMED production
    | facts rather than making an administrator retype them, but seed as a
    | DRAFT and never present a draft as approved.
    |
    | The three values below are the ones SEC-DEC-036 records as verified -
    | server Singapore, backups Singapore, no replication outside Singapore -
    | each confirmed separately, because a server's country is not the same as
    | its backups' country.
    |
    | Every cross-geo switch defaults to FALSE. CLAUDE.md requires cross-geo
    | processing, storage and AI or conversation history to default OFF.
    */
    'sovereignty_seed' => [
        'storage_geography' => 'sg',
        'processing_geography' => 'sg',
        'ai_processing_geography' => 'not_determined',
        'backup_geography' => 'sg',
        'external_replication' => 'none',
        'cross_geo_storage' => false,
        'cross_geo_processing' => false,
        'cross_geo_ai' => false,
        'cross_geo_conversation_history' => false,
        'source_note' => 'Seeded from the confirmed production facts recorded as SEC-DEC-036 on '
            .'25 August 2026: the control plane is hosted in Singapore, its backups are in Singapore, '
            .'and there is no replication outside Singapore. AI processing geography is left undetermined '
            .'because no AI service has been provisioned yet. This is a DRAFT and has not been approved.',
    ],

    'external_replication' => [
        'none' => 'None',
        'same_geography' => 'Within the same geography only',
        'cross_geography' => 'Across geographies',
        'not_determined' => 'Not determined',
    ],

    /*
    | The personal data categories a fresh install starts with.
    |
    | These describe what THIS application holds about people, which is the
    | question PDPA-01 has to answer. They were written from a re-scan of the
    | live schema rather than from a template: personal data appears in 19 of
    | the 23 tables, not the five DEC-002 named.
    |
    | `code` is the stable identifier and never changes. `tables` names where
    | the category lives, which is what makes the R1.4c collector coverage test
    | possible - a table claimed by no category and named in no exclusion list
    | fails that test.
    |
    | No retention period and no lawful basis appears here. Both are compliance
    | judgements and both arrive with PDPA-03 in R1.4b, where they are stored
    | per category and shown as Not Configured until somebody sets them.
    */
    'personal_data_categories' => [
        [
            'code' => 'account_identity',
            'name' => 'Account identity',
            'description' => 'The name, email address and account state of a person who can sign in, '
                .'together with the organisation, business unit and team they belong to.',
            'classification' => DataClassification::Confidential,
            'contains_sensitive' => false,
            'tables' => ['users'],
        ],
        [
            'code' => 'federated_identity',
            'name' => 'Federated identity',
            'description' => 'The Microsoft Entra object and tenant identifiers that link a SemantIQ '
                .'account to a directory account. No credential is held.',
            'classification' => DataClassification::Confidential,
            'contains_sensitive' => false,
            'tables' => ['users'],
        ],
        [
            'code' => 'access_rights',
            'name' => 'Access rights held',
            'description' => 'The platform tier, assigned roles and business-domain entitlements a '
                .'person holds, and the access reviews those were examined in.',
            'classification' => DataClassification::Confidential,
            'contains_sensitive' => false,
            'tables' => ['user_roles', 'domain_entitlements', 'access_reviews', 'access_review_items'],
        ],
        [
            'code' => 'activity_record',
            'name' => 'Activity record',
            'description' => 'What a person did in SemantIQ and when: sign-ins, refusals, configuration '
                .'changes and administrative actions, with the reason given for a high-risk change.',
            'classification' => DataClassification::Restricted,
            'contains_sensitive' => false,
            'tables' => ['audit_events'],
        ],
        [
            'code' => 'network_identifier',
            'name' => 'Network identifier',
            'description' => 'The IP address and browser user agent recorded against an activity record. '
                .'Held separately because it is rarely needed and is disclosed under its own permission.',
            'classification' => DataClassification::Restricted,
            'contains_sensitive' => false,
            'tables' => ['audit_events'],
        ],
        [
            'code' => 'accountability_contact',
            'name' => 'Accountability contact',
            'description' => 'The named data owner, privacy contact and security contact recorded on the '
                .'organisation profile. A name or a role, never a credential.',
            'classification' => DataClassification::Internal,
            'contains_sensitive' => false,
            'tables' => ['organisations'],
        ],
        [
            'code' => 'authentication_state',
            'name' => 'Sign-in state',
            'description' => 'Password reset tokens and server-side session records, where the '
                .'deployment stores sessions in the database. Held only while it is in use.',
            'classification' => DataClassification::Restricted,
            'contains_sensitive' => false,
            'tables' => ['password_reset_tokens', 'sessions'],
        ],
    ],

    /*
    | ADM-016. Which aspect of the sovereignty profile an exception departs
    | from. A closed list, because an exception to "some stuff" cannot be
    | reviewed, reported on, or compared against the profile it departs from.
    */
    'exception_aspects' => [
        'storage' => 'Storage geography',
        'processing' => 'Processing geography',
        'ai_processing' => 'AI processing geography',
        'backup' => 'Backup geography',
        'replication' => 'Replication outside the geography',
        'other' => 'Something else, described in the scope note',
    ],

    /*
    | PDPA-03. WHEN THE RETENTION CLOCK STARTS.
    |
    | Without this a period is unusable. "Three years" from what? Account
    | closure, last activity, record creation and contract end are four
    | different dates and produce four different answers, and the difference
    | between them is often years.
    */
    'retention_start_events' => [
        'record_created' => 'When the record was created',
        'last_activity' => 'The last time the person used SemantIQ',
        'account_closed' => 'When the account was closed',
        'relationship_ended' => 'When the relationship with the person ended',
        'contract_ended' => 'When the governing contract ended',
        'event_occurred' => 'When the event the record describes occurred',
    ],

    /*
    | PDPA-03. What happens when the period runs out.
    |
    | Deletion is one option among several, and it is deliberately not the
    | default. Anonymising keeps the analytical value without keeping the
    | person; archiving moves it out of reach without destroying it; review
    | means somebody looks before anything happens.
    |
    | NOTHING IN GATE 4 EXECUTES ANY OF THESE. SEC-DEC-038. The column records
    | an intention.
    */
    'retention_disposal_actions' => [
        'review' => 'Review, then decide',
        'anonymise' => 'Anonymise, keeping no link to a person',
        'archive' => 'Archive out of the working system',
        'delete' => 'Delete',
        'retain' => 'Retain, under a stated obligation',
    ],

    /*
    | ADM-013. The four functional views, as FILTER PRESETS over one table.
    | DEC-004, approved 24 August 2026.
    |
    | Each preset is a set of filters, not a screen and not a query of its own.
    | `modules` and `action_prefixes` narrow; an empty list means no narrowing
    | on that dimension.
    |
    | THE PRESETS MUST NOT PARTITION THE TABLE. A reader who picks the wrong one
    | must still be able to find an event by widening to All Events, so the
    | presets deliberately overlap rather than carving the trail into four
    | disjoint pieces. `AuditLogTest` asserts every event appears under All
    | Events.
    */
    'audit_views' => [
        'all' => [
            'label' => 'All Events',
            'help' => 'Everything recorded, newest first.',
            'modules' => [],
            'action_prefixes' => [],
        ],
        'user_activity' => [
            'label' => 'User Activity',
            'help' => 'Who signed in, who was refused, and what happened to their session.',
            'modules' => [],
            'action_prefixes' => ['auth.', 'user.'],
        ],
        'administrative' => [
            'label' => 'Administrative Changes',
            'help' => 'Changes to organisations, people, roles, permissions and entitlements.',
            'modules' => ['Identity'],
            'action_prefixes' => [],
        ],
        'security' => [
            'label' => 'Security Changes',
            'help' => 'Authentication and session policy, API security, and credential references.',
            'modules' => ['Security'],
            'action_prefixes' => [],
        ],
        'configuration' => [
            'label' => 'Configuration Changes',
            'help' => 'Platform settings, feature flags and governance policy.',
            'modules' => ['Platform', 'Governance'],
            'action_prefixes' => [],
        ],
    ],

    /*
    | The classification each category may carry, and what each one means to
    | somebody choosing between them. A codified list rather than free text, per
    | CLAUDE.md's schema rules.
    */
    'classifications' => [
        'public' => 'Can be published without harm.',
        'internal' => 'For people inside the organisation. Not for publication.',
        'confidential' => 'Limited to those with a business need. Disclosure would cause harm.',
        'restricted' => 'The most sensitive. Disclosure would cause serious harm, and access is '
            .'individually justified.',
    ],
];
