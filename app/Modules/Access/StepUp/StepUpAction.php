<?php

declare(strict_types=1);

namespace App\Modules\Access\StepUp;

/**
 * The five actions that require step-up re-authentication - D-73.
 *
 * FIVE, AND ONLY FIVE. Each is an action that changes who holds privileged
 * authority, or that raises information above the level everybody else sees.
 * N-S1 breaks it by dropping one.
 */
enum StepUpAction: string
{
    case GrantSystemAdministrator = 'grant_system_administrator';
    case RevokeSystemAdministrator = 'revoke_system_administrator';
    case GrantOrganisationAdministrator = 'grant_organisation_administrator';

    /*
     * P1-07 §12 - A BOUNDED CORRECTION TO P1-05, NOT A NEW POLICY.
     *
     * RoleCatalogue::requiringStepUp() has always returned System Administrator
     * AND Organisation Administrator, and the grant side has both. The revoke
     * side had only the System Administrator case, so revoking an Organisation
     * Administrator - removing somebody who holds AccessAdmin - went through
     * with no fresh sign-in, contradicting the catalogue the whole unit reads.
     *
     * Surfaced by P1-07, which cannot enforce D-92 without it. The guard that
     * would have caught it is StepUpActionCoversRequiringStepUpTest: it derives
     * the requirement from requiringStepUp() rather than listing the cases, so
     * a role added there later without both actions fails immediately.
     */
    case RevokeOrganisationAdministrator = 'revoke_organisation_administrator';
    case SelfGrant = 'self_grant';
    case GrantRestrictedSensitivity = 'grant_restricted_sensitivity';

    /*
     * P1-07 - D-92. THREE ACTIONS OF ITS OWN, rather than re-using the two
     * revoke actions above.
     *
     * A review decision has to do something the Roles & Access revoke does not:
     * mark the review item decided. The stored row therefore has to say which
     * surface began the confirmation, because StepUpController::perform routes
     * on the action alone and every parameter it uses comes from that row.
     * Re-using RevokeSystemAdministrator would have returned from Microsoft
     * into P1-05's performer, revoked the access and left the item pending
     * forever - a decision the evidence would never show.
     *
     * The decision itself (retain or revoke) is NOT stored here. It is written
     * on the review item before the redirect, so nothing about a P1-07 decision
     * lives in a P1-05 table.
     */
    case ReviewRevokePrivileged = 'review_revoke_privileged';
    case RevokeRestrictedEntitlement = 'revoke_restricted_entitlement';
    case SelfReview = 'self_review';

    /** The P1-07 review actions, named once so nothing has to list them twice. */
    public function isReviewDecision(): bool
    {
        return in_array($this, [
            self::ReviewRevokePrivileged,
            self::RevokeRestrictedEntitlement,
            self::SelfReview,
        ], true);
    }

    /** What the administrator is told they are about to confirm. */
    public function description(): string
    {
        return match ($this) {
            self::GrantSystemAdministrator => 'grant the System Administrator role',
            self::RevokeSystemAdministrator => 'remove the System Administrator role',
            self::GrantOrganisationAdministrator => 'grant the Organisation Administrator role',
            self::RevokeOrganisationAdministrator => 'remove the Organisation Administrator role',
            self::SelfGrant => 'grant access to yourself',
            self::GrantRestrictedSensitivity => 'allow access to restricted information',
            self::ReviewRevokePrivileged => 'remove privileged access after review',
            self::RevokeRestrictedEntitlement => 'remove access to restricted information after review',
            self::SelfReview => 'review your own access',
        };
    }
}
