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
        RoleCode::OrganisationAdministrator->value => [
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
