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

    /*
     * P1-10 - D-159. TWO ACTIONS, AND THEY ARE ABOUT A CREDENTIAL RATHER THAN
     * A ROLE.
     *
     * Every action above changes who holds privileged authority. These change
     * a stored credential the deployment authenticates WITH - the mail
     * password, the AI key, the Fabric client secret - which is the same class
     * of thing from the other direction: somebody who can silently replace the
     * mail credential can redirect the deployment's outbound mail, and somebody
     * who can replace the Fabric secret can point it at a directory they
     * control.
     *
     * REPLACING AND REMOVING ARE SEPARATE, not one "change" action, because the
     * completion handler must not have to infer which it is from whether a
     * payload happens to be present. An absent payload would then mean
     * "removal" - and an absent payload is also what a failed decrypt looks
     * like.
     *
     * ESTABLISHING a credential for the first time does NOT require step-up.
     * There is nothing to take away, the administrator already holds
     * PlatformAdmin, and requiring it would make First-Run unsatisfiable on a
     * deployment that has no Microsoft yet.
     *
     * IDENTITY IS NOT IN THESE TWO. The normal console has no identity write
     * path at all - P1-02 owns it - so neither of these could authorise one.
     * First-Run's identity writes are the Bootstrap principal's, and those
     * reconfirm the local password instead, because Microsoft step-up cannot
     * exist before Microsoft does.
     */
    case ReplaceIntegrationSecret = 'replace_integration_secret';
    case RemoveIntegrationSecret = 'remove_integration_secret';

    /*
     * P1-02, GATE C ROUND 3. CHANGING MICROSOFT SIGN-IN AFTER INSTALLATION.
     *
     * It has an action of its own rather than reusing ReplaceIntegrationSecret,
     * for the reason P1-07 established when it refused to reuse the revoke
     * actions: the completion handler must do something the others do not.
     * Confirming a credential change APPLIES it. Confirming an identity change
     * must VERIFY the candidate against Microsoft first and apply it only if
     * that answers - because the thing being changed is the only way anybody
     * signs in, and a configuration that does not work locks every
     * administrator out of the deployment that holds it.
     *
     * Routing that through the integration performer would apply an unverified
     * directory and leave the deployment unreachable, with the evidence saying
     * it succeeded.
     *
     * THE CIRCULARITY IS DELIBERATE AND IT IS SAFE. Changing sign-in requires
     * signing in, which means the CURRENT configuration must still work to
     * authorise replacing it - so a broken configuration cannot be "fixed" from
     * here by somebody who cannot already get in. That is the correct
     * direction: recovery from a broken directory is the Bootstrap
     * administrator's job, not a console screen's.
     */
    case ReconfigureIdentity = 'reconfigure_identity';

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
            self::ReplaceIntegrationSecret => 'replace a saved connection credential',
            self::RemoveIntegrationSecret => 'remove a saved connection credential',
            self::ReconfigureIdentity => 'change how people sign in with Microsoft',
        };
    }
}
