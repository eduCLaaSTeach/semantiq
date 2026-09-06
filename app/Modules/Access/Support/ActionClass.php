<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

/**
 * The five action classes every protected route must declare.
 *
 * BUSINESS_DATA is the only one that reaches grant-path evaluation. That is not
 * a policy written in prose - it is the structural fact the engine is built on,
 * and it is what makes "an administrator receives no business data" true by
 * construction rather than by an empty result set that would keep passing after
 * the boundary was removed.
 *
 * There is deliberately no default. A protected route that declares no class
 * fails closed, because the alternative is a route silently inheriting whatever
 * the last developer thought was reasonable.
 */
enum ActionClass: string
{
    case PlatformAdmin = 'platform_admin';
    case OrgAdmin = 'org_admin';
    case AccessAdmin = 'access_admin';
    case EvidenceRead = 'evidence_read';
    case BusinessData = 'business_data';

    /**
     * Whether this class requires a complete grant path - role, entitlement,
     * scope and ceiling. Exactly one does.
     */
    public function requiresGrantPath(): bool
    {
        return $this === self::BusinessData;
    }

    public function label(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Platform administration',
            self::OrgAdmin => 'Organisation administration',
            self::AccessAdmin => 'Access administration',
            self::EvidenceRead => 'Evidence and audit read',
            self::BusinessData => 'Business information',
        };
    }
}
