<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

/**
 * THE CATALOGUE. One immutable constant, the same shape as P1-04's
 * BaselineDomains - and for the same reason: a table anybody can edit is a
 * table somebody eventually edits.
 *
 * D-51 to D-54 all answered "no". There is no `roles` table, no custom role, no
 * rename, no editable capability set and no role deactivation. Nothing about a
 * role is manageable at runtime, so nothing about a role is stored in a row.
 *
 * THE FIRST FOUR ACTION CLASSES GRANT NO BUSINESS DATA AT ALL. That is not
 * prose - BusinessData is the only class the engine routes into grant-path
 * evaluation, so "System Administrator receives no business rows" holds by
 * construction. A test that merely observed an empty result would keep passing
 * after the boundary was removed; the guard breaks the construction instead.
 *
 * DOMAIN OWNER IS NOT THE SOURCE OF DOMAIN ACCOUNTABILITY. P1-04's
 * business_domain_owners is, and neither may be derived from the other. This
 * role permits BusinessData like any other business role - through explicit
 * grants only, never from owning anything.
 */
final class RoleCatalogue
{
    /**
     * Which action classes each role permits.
     *
     * Read it as: holding this role admits these classes to evaluation. For the
     * four administration classes that is the whole decision. For BusinessData
     * it is only the first of four dimensions - a complete path still needs an
     * entitlement, a scope and a ceiling.
     *
     * @var array<string, list<ActionClass>>
     */
    private const MATRIX = [
        RoleCode::SystemAdministrator->value => [
            ActionClass::PlatformAdmin,
            ActionClass::OrgAdmin,
            ActionClass::AccessAdmin,
            ActionClass::EvidenceRead,
        ],
        /*
         * CL-12 AMENDED. THE PRODUCT OWNER RULED FULL ADMINISTRATION ACCESS,
         * and PlatformAdmin is how that ruling is expressed.
         *
         * This is the only line in the repository that widens an authorisation
         * for CL-12, and it widens a real one. An Organisation Administrator
         * can now reach Identity & SSO, System Health and Integrations - which
         * means writing the Entra client secret and the deployment-wide
         * integration credentials for the identity, email, ai and fabric
         * families. The amended Gate B design named that consequence
         * explicitly and the Product Owner chose it knowing so.
         *
         * WHAT THIS DOES NOT DO, and each is asserted rather than asserted of:
         *
         *   - BusinessData is ABSENT. Administration authority is still not
         *     business-data authority; that separation is the architectural
         *     rule CL-12 was careful not to break.
         *   - It does not make them a System Administrator. grantableBy()
         *     below still excludes system_administrator, so the
         *     privilege-escalation path stays closed.
         *   - It does not touch requiringStepUp(), so granting either
         *     administrator role still re-authenticates.
         *
         * The two roles now hold the SAME four action classes. What still
         * separates them is grantability and navigation: a System
         * Administrator sees the whole roadmap, an Organisation Administrator
         * sees the two administration areas and not SemantIQ Workplace.
         */
        RoleCode::OrganisationAdministrator->value => [
            ActionClass::PlatformAdmin,
            ActionClass::OrgAdmin,
            ActionClass::AccessAdmin,
            ActionClass::EvidenceRead,
        ],
        RoleCode::Executive->value => [ActionClass::BusinessData],
        RoleCode::DomainOwner->value => [ActionClass::BusinessData],
        RoleCode::Manager->value => [ActionClass::BusinessData],
        RoleCode::BusinessUser->value => [ActionClass::BusinessData],
        RoleCode::Auditor->value => [ActionClass::EvidenceRead],
    ];

    /**
     * The four administration classes. Named once, so "administration authority
     * never implies business-data authority" is checkable rather than asserted.
     *
     * @return list<ActionClass>
     */
    public static function administrationClasses(): array
    {
        return [
            ActionClass::PlatformAdmin,
            ActionClass::OrgAdmin,
            ActionClass::AccessAdmin,
            ActionClass::EvidenceRead,
        ];
    }

    /** @return list<RoleCode> */
    public static function roles(): array
    {
        return RoleCode::cases();
    }

    /** @return list<ActionClass> */
    public static function classesFor(RoleCode $role): array
    {
        return self::MATRIX[$role->value];
    }

    public static function permits(RoleCode $role, ActionClass $class): bool
    {
        return in_array($class, self::MATRIX[$role->value], true);
    }

    /**
     * Which roles could admit this class to evaluation. Used to narrow candidate
     * assignments before any row is read - the narrowing of §6.5, never a
     * widening.
     *
     * @return list<RoleCode>
     */
    public static function rolesPermitting(ActionClass $class): array
    {
        return array_values(array_filter(
            RoleCode::cases(),
            static fn (RoleCode $role): bool => self::permits($role, $class),
        ));
    }

    /**
     * Which roles the actor may grant or revoke.
     *
     * ORGANISATION ADMINISTRATOR CAN NEVER GRANT system_administrator. That is
     * the privilege-escalation path this whole unit exists to close, and it is
     * expressed as an allow-list rather than a denied case: a new role added to
     * the catalogue is not grantable by an Organisation Administrator until
     * somebody says so here.
     *
     * @return list<RoleCode>
     */
    public static function grantableBy(RoleCode $actorRole): array
    {
        return match ($actorRole) {
            RoleCode::SystemAdministrator => RoleCode::cases(),
            RoleCode::OrganisationAdministrator => [
                RoleCode::Executive,
                RoleCode::DomainOwner,
                RoleCode::Manager,
                RoleCode::BusinessUser,
                RoleCode::Auditor,
                RoleCode::OrganisationAdministrator,
            ],
            default => [],
        };
    }

    /**
     * Granting or revoking these requires step-up re-authentication - §9.1.
     *
     * @return list<RoleCode>
     */
    public static function requiringStepUp(): array
    {
        return [RoleCode::SystemAdministrator, RoleCode::OrganisationAdministrator];
    }
}
