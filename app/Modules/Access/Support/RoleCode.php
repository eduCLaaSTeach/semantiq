<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

/**
 * The seven roles, by fixed code. D-51 to D-54 all answered "no", so nothing
 * about a role is manageable and there is no `roles` table.
 *
 * A code is security vocabulary, not branding. D-52 was changed during the PLAN
 * review for exactly that reason: a domain's name is a label an administrator
 * may reasonably want to change, and a role's name is not. "Super Admin" must
 * be unreachable, so the display name lives here beside the code rather than in
 * a row somebody can edit.
 *
 * SYSTEM ADMINISTRATOR IS PLATFORM-SCOPED. It is the only case that may be held
 * with a NULL organisation, because bootstrap must create one before a Company
 * Profile exists. Every other role requires an organisation.
 */
enum RoleCode: string
{
    case SystemAdministrator = 'system_administrator';
    case OrganisationAdministrator = 'organisation_administrator';
    case Executive = 'executive';
    case DomainOwner = 'domain_owner';
    case Manager = 'manager';
    case BusinessUser = 'business_user';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdministrator => 'System Administrator',
            self::OrganisationAdministrator => 'Organisation Administrator',
            self::Executive => 'Executive',
            self::DomainOwner => 'Domain Owner / Director',
            self::Manager => 'Manager',
            self::BusinessUser => 'Business User',
            self::Auditor => 'Auditor',
        };
    }

    /**
     * Platform-scoped roles may be held with a NULL organisation. Exactly one
     * is, and adding a second here would let an organisation-scoped role escape
     * its tenancy boundary.
     */
    public function isPlatformScoped(): bool
    {
        return $this === self::SystemAdministrator;
    }

    /**
     * What an administrator is told this role does. Business language, because
     * it reaches a screen.
     */
    public function summary(): string
    {
        return match ($this) {
            self::SystemAdministrator => 'Full platform and system administration, including Identity and single sign-on. No business data.',
            self::OrganisationAdministrator => 'Organisation, people, groups, domains and access administration. No platform authority and no business data.',
            self::Executive => 'Business information only, through explicit domain entitlements.',
            self::DomainOwner => 'A business role. It confers no automatic entitlement and no access from owning a domain.',
            self::Manager => 'Business information only, for teams and business units that have been explicitly assigned.',
            self::BusinessUser => 'Business information only, through explicit domain entitlements.',
            self::Auditor => 'Read-only access to security evidence across the organisation. No business data without a separate entitlement.',
        };
    }
}
