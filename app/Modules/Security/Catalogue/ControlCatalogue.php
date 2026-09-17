<?php

declare(strict_types=1);

namespace App\Modules\Security\Catalogue;

use App\Modules\Security\Posture\ControlKind;
use App\Modules\Security\Posture\ControlScope;
use App\Modules\Security\Posture\ExceptionKind;

/**
 * THE CATALOGUE. One immutable constant, the same shape as P1-04's
 * BaselineDomains and P1-05's RoleCatalogue - and for the same reason: a table
 * anybody can edit is a table somebody eventually edits, and this one decides
 * what a deployment is asked about.
 *
 * CATALOGUE ORDER IS THE ONLY ORDER. Rows are never sorted by state, for any
 * viewer. Sorting by severity would turn a withheld row's POSITION into its
 * value, which is the leak channel most likely to arrive as a well-meaning
 * improvement - "show the problems first" sounds reasonable and is a disclosure.
 * N-SS26 breaks it by sorting.
 *
 * EVERY ENUMERATED CONTROL MUST HAVE AN EVALUATOR. N-SS7 asserts it, so a
 * control added here without a branch in PostureEvaluator fails the build
 * rather than rendering as a missing row. A row is never omitted to avoid an
 * awkward state: omission is the failure mode that makes a posture screen lie,
 * because the reader counts what they see.
 *
 * D-83: NO ARTIFICIAL not_applicable ROW. Capabilities genuinely outside
 * Release 1 - data classification, Fabric security - are stated as absent on
 * the screen rather than dressed up as controls, so no catalogue entry carries
 * NotApplicable in Release 1. The state remains in the model and in the
 * fixtures because the aggregation contract must be right before the first real
 * instance arrives, not retrofitted around it.
 */
final class ControlCatalogue
{
    // ---- Secure Baseline ---------------------------------------------------

    public const IDENTITY_TRUST = 'B-1';

    public const APPROVED_PROVIDERS = 'B-2';

    public const SIGN_IN_REACHABLE = 'B-3';

    public const SESSION_POLICY = 'B-4';

    public const INACTIVE_ACCOUNTS = 'B-5';

    public const ROUTE_COVERAGE = 'B-6';

    public const GRANT_REQUIRED = 'B-7';

    public const LAST_ADMINISTRATOR = 'B-8';

    public const STEP_UP_LOCAL = 'B-9a';

    public const STEP_UP_EXTERNAL = 'B-9b';

    public const ENCRYPTION = 'B-10';

    public const WEB_EXPOSURE = 'B-11';

    public const BACKUPS = 'B-12';

    // ---- Privileged Access Health -----------------------------------------

    public const ADMINISTRATOR_COUNT = 'PR-1';

    public const ORGANISATION_ADMINISTRATORS = 'PR-2';

    public const PRIVILEGED_WITH_DATA = 'PR-3';

    public const RESTRICTED_GRANTS = 'PR-4';

    public const INACTIVE_WITH_ASSIGNMENTS = 'PR-5';

    public const INCOMPLETE_PATHS = 'PR-6';

    public const OWNERS_WITHOUT_ENTITLEMENT = 'PR-7';

    public const BROAD_SCOPES = 'PR-8';

    public const STEP_UP_LOCAL_PRIVILEGED = 'PR-9a';

    public const STEP_UP_EXTERNAL_PRIVILEGED = 'PR-9b';

    public const INACTIVE_GATE = 'PR-10';

    // ---- The carried P1-02 gate -------------------------------------------

    public const SSO_RECHECK = 'P1-02-GATE';

    /** @return list<Control> */
    public static function baseline(): array
    {
        $identity = ['identity.entra', 'Identity & SSO'];
        $health = ['identity.health', 'Identity & SSO — SSO Health'];

        return [
            new Control(
                self::IDENTITY_TRUST,
                'Everybody signs in with Microsoft',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::Unresolved,
                ...$identity,
            ),
            new Control(
                self::APPROVED_PROVIDERS,
                'Only approved sign-in providers are registered',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::Unresolved,
                'identity.providers',
                'Identity & SSO — Other Identity Providers',
            ),
            new Control(
                self::SIGN_IN_REACHABLE,
                'Sign-in is reachable right now',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::VerificationIncomplete,
                ...$health,
            ),
            new Control(
                self::SESSION_POLICY,
                'Sessions expire and are re-checked on every request',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::Unresolved,
                'identity.session-policy',
                'Identity & SSO — Session Policy',
            ),
            new Control(
                self::INACTIVE_ACCOUNTS,
                'People who have left cannot use the product',
                ControlKind::PostureControl,
                ControlScope::Organisation,
                ExceptionKind::Unresolved,
                'people.users',
                'Users & Groups',
            ),
            new Control(
                self::ROUTE_COVERAGE,
                'Every administration screen is checked by the server',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::Unresolved,
            ),
            new Control(
                self::GRANT_REQUIRED,
                'Business information is refused unless a complete grant exists',
                ControlKind::PostureControl,
                ControlScope::Organisation,
                ExceptionKind::Unresolved,
                'access.index',
                'Roles & Access',
            ),
            new Control(
                self::LAST_ADMINISTRATOR,
                'The last System Administrator cannot be removed',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::Unresolved,
                'access.index',
                'Roles & Access',
            ),
            new Control(
                self::STEP_UP_LOCAL,
                'Privileged changes need a fresh Microsoft sign-in',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::Unresolved,
                'access.index',
                'Roles & Access',
            ),
            new Control(
                self::STEP_UP_EXTERNAL,
                'Microsoft accepts the return from a fresh sign-in',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::VerificationIncomplete,
            ),
            new Control(
                self::ENCRYPTION,
                'Information is encrypted in transit and at rest',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::AcceptedLimitation,
            ),
            new Control(
                self::WEB_EXPOSURE,
                'Internal files are not reachable from the web',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::AcceptedLimitation,
            ),
            new Control(
                self::BACKUPS,
                'Information is backed up and can be restored',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::AcceptedLimitation,
            ),
        ];
    }

    /** @return list<Control> */
    public static function privileged(): array
    {
        $access = ['access.index', 'Roles & Access'];

        return [
            new Control(
                self::ADMINISTRATOR_COUNT,
                'Active System Administrators',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::Unresolved,
                ...$access,
            ),
            new Control(
                self::PRIVILEGED_WITH_DATA,
                'Administrators who also hold business information access',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::Unresolved,
                ...$access,
            ),
            new Control(
                self::INCOMPLETE_PATHS,
                'Access that was set up but grants nothing',
                ControlKind::PostureControl,
                ControlScope::Organisation,
                ExceptionKind::NotYetConfigured,
                ...$access,
            ),
            new Control(
                self::STEP_UP_LOCAL_PRIVILEGED,
                'Privileged changes need a fresh Microsoft sign-in',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::Unresolved,
                ...$access,
            ),
            new Control(
                self::STEP_UP_EXTERNAL_PRIVILEGED,
                'Microsoft accepts the return from a fresh sign-in',
                ControlKind::PostureControl,
                ControlScope::Platform,
                ExceptionKind::VerificationIncomplete,
            ),
            new Control(
                self::INACTIVE_GATE,
                'People who have left are refused by the access check itself',
                ControlKind::PostureControl,
                ControlScope::Organisation,
                ExceptionKind::Unresolved,
                ...$access,
            ),

            // ---- Informational metrics. COUNT AND CONTEXT ONLY, NO STATE. ----
            new Control(
                self::ORGANISATION_ADMINISTRATORS,
                'Organisation Administrators',
                ControlKind::InformationalMetric,
                ControlScope::Organisation,
                ExceptionKind::Unresolved,
                ...$access,
            ),
            new Control(
                self::RESTRICTED_GRANTS,
                'Grants that allow restricted information',
                ControlKind::InformationalMetric,
                ControlScope::Organisation,
                ExceptionKind::Unresolved,
                ...$access,
            ),
            new Control(
                self::INACTIVE_WITH_ASSIGNMENTS,
                'People who have left whose access is preserved',
                ControlKind::InformationalMetric,
                ControlScope::Organisation,
                ExceptionKind::Unresolved,
                'people.users',
                'Users & Groups',
            ),
            new Control(
                self::OWNERS_WITHOUT_ENTITLEMENT,
                'Domain owners without an entitlement of their own',
                ControlKind::InformationalMetric,
                ControlScope::Organisation,
                ExceptionKind::Unresolved,
                'domains.index',
                'Business Domains',
            ),
            new Control(
                self::BROAD_SCOPES,
                'Grants covering a whole domain',
                ControlKind::InformationalMetric,
                ControlScope::Organisation,
                ExceptionKind::Unresolved,
                ...$access,
            ),
        ];
    }

    /**
     * The carried P1-02 gate. A control in its own right, so it appears in
     * Exceptions honestly rather than as a paragraph somebody can delete.
     */
    public static function carriedGate(): Control
    {
        return new Control(
            self::SSO_RECHECK,
            'Provider-wide sign-in re-check',
            ControlKind::PostureControl,
            ControlScope::Platform,
            ExceptionKind::VerificationIncomplete,
        );
    }

    /** @return list<Control> */
    public static function all(): array
    {
        return [...self::baseline(), ...self::privileged(), self::carriedGate()];
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return array_map(static fn (Control $c): string => $c->id, self::all());
    }
}
