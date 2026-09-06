<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

use RuntimeException;

/**
 * A refused Roles & Access operation, carrying a stable reason and a message
 * written for an administrator.
 *
 * The same shape as P1-01's StructureViolation, P1-03's PeopleViolation and
 * P1-04's DomainViolation, for the same reason: the screen renders THIS
 * message, never an exception, because rendering an exception is how a stack
 * trace or a database constraint reaches a browser.
 *
 * Every sentence is a complete instruction - what happened AND what to do
 * instead.
 */
final class AccessViolation extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function soleAdministrator(): self
    {
        return new self(
            'sole_administrator',
            'This is the only active System Administrator. Add or retain another before removing this one.'
        );
    }

    public static function organisationRequired(RoleCode $role): self
    {
        return new self(
            'organisation_required',
            'The '.$role->label().' role must be granted within an organisation. '
            .'Assign this person to the organisation first.'
        );
    }

    public static function platformRoleWithOrganisation(): self
    {
        return new self(
            'platform_role_with_organisation',
            'The System Administrator role is granted across the whole platform, not within one organisation.'
        );
    }

    public static function roleAlreadyHeld(RoleCode $role): self
    {
        return new self(
            'role_already_held',
            'This person already holds the '.$role->label().' role. '
            .'Revoke it first if you want to record a new period.'
        );
    }

    public static function roleNotHeld(RoleCode $role): self
    {
        return new self(
            'role_not_held',
            'This person does not currently hold the '.$role->label().' role.'
        );
    }

    public static function roleNotGrantable(RoleCode $role): self
    {
        return new self(
            'role_not_grantable',
            'You cannot grant the '.$role->label().' role. Ask a System Administrator.'
        );
    }

    public static function userInactive(): self
    {
        return new self(
            'user_inactive',
            'That person\'s account is not active. Reactivate it before granting access.'
        );
    }

    public static function userOutsideOrganisation(): self
    {
        return new self(
            'user_outside_organisation',
            'That person is not part of this organisation. Assign them to it first.'
        );
    }

    public static function entitlementAlreadyHeld(): self
    {
        return new self(
            'entitlement_already_held',
            'This role assignment is already entitled to that domain. Open it to change its scope or sensitivity.'
        );
    }

    public static function entitlementNotCurrent(): self
    {
        return new self('entitlement_not_current', 'That entitlement has already been revoked.');
    }

    public static function domainOutsideOrganisation(): self
    {
        return new self('domain_outside_organisation', 'That domain belongs to a different organisation.');
    }

    public static function scopeTargetRequired(ScopeType $scope): self
    {
        return new self(
            'scope_target_required',
            'Choose which '.mb_strtolower($scope->label()).' this scope applies to.'
        );
    }

    public static function scopeTargetNotApplicable(ScopeType $scope): self
    {
        return new self(
            'scope_target_not_applicable',
            $scope->label().' scope applies to the whole domain, so it cannot name a team or business unit.'
        );
    }

    public static function scopeTargetOutsideOrganisation(): self
    {
        return new self(
            'scope_target_outside_organisation',
            'That team or business unit belongs to a different organisation.'
        );
    }

    public static function duplicateScope(): self
    {
        return new self(
            'duplicate_scope',
            'That scope is already assigned to this entitlement. Scopes add together, so assigning it twice grants nothing further.'
        );
    }

    public static function scopeNotCurrent(): self
    {
        return new self('scope_not_current', 'That scope has already been revoked.');
    }

    public static function stepUpRequired(): self
    {
        return new self(
            'step_up_required',
            'This action needs you to confirm your identity with Microsoft before it can be applied.'
        );
    }

    public static function stepUpInvalid(): self
    {
        return new self(
            'step_up_invalid',
            'That confirmation is no longer valid. Start the action again.'
        );
    }

    public static function selfGrantRefused(): self
    {
        return new self(
            'self_grant_refused',
            'You cannot grant yourself this role. Ask another System Administrator.'
        );
    }
}
