<?php

declare(strict_types=1);

namespace App\Modules\Platform\Identity;

use RuntimeException;

/**
 * A typed authentication failure carrying the refusal state to show.
 *
 * The reason is for the security log; the state is for the browser. They are
 * separate on purpose - the log needs to say which check failed, and the user
 * must not be told, because that difference is what lets someone probe the
 * boundary.
 */
final class AuthenticationFailed extends RuntimeException
{
    public const STATE_UNAVAILABLE = 'sign-in-unavailable';

    public const STATE_ACCESS_DENIED = 'access-denied';

    public const STATE_NOT_ASSIGNED = 'access-not-assigned';

    public const STATE_INACTIVE = 'account-inactive';

    private function __construct(
        public readonly string $state,
        public readonly string $reason,
    ) {
        parent::__construct($reason);
    }

    public static function protocol(string $reason): self
    {
        return new self(self::STATE_UNAVAILABLE, $reason);
    }

    public static function tenant(string $reason = 'tenant_not_approved'): self
    {
        return new self(self::STATE_ACCESS_DENIED, $reason);
    }

    public static function notAssigned(): self
    {
        return new self(self::STATE_NOT_ASSIGNED, 'unknown_identity');
    }

    /**
     * An inactive account, refused during SIGN-IN.
     *
     * The state is deliberately access-not-assigned, not account-inactive. To an
     * anonymous caller the two must be indistinguishable: if "this account is
     * inactive" and "this account has no access" led to different pages, the
     * difference would tell an attacker which addresses correspond to real
     * SemantIQ accounts. The security log still records the true reason.
     *
     * The distinct account-inactive state remains for a user deactivated
     * mid-session, where they already know the account exists and no
     * enumeration is possible.
     */
    public static function inactive(): self
    {
        return new self(self::STATE_NOT_ASSIGNED, 'inactive');
    }
}
