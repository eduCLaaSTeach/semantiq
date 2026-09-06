<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

/**
 * The sensitivity ceiling on one entitlement - D-60.
 *
 * There is NO person-level ceiling. Two independent cap paths would leave an
 * entitlement raised to Confidential still capped by an invisible person-level
 * Standard, on a screen that could not explain why.
 *
 * Above the ceiling the engine DENIES - D-63. It does not redact. A report that
 * quietly drops a column is one whose reader believes they are seeing
 * everything.
 */
enum Sensitivity: string
{
    case Standard = 'standard';
    case Confidential = 'confidential';
    case Restricted = 'restricted';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Confidential => 'Confidential',
            self::Restricted => 'Restricted',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Standard => 1,
            self::Confidential => 2,
            self::Restricted => 3,
        };
    }

    /** Whether a ceiling at this level permits a request at $requested. */
    public function permits(self $requested): bool
    {
        return $requested->rank() <= $this->rank();
    }

    /**
     * Granting this level requires step-up re-authentication. Exactly one does.
     */
    public function requiresStepUpToGrant(): bool
    {
        return $this === self::Restricted;
    }

    public function description(): string
    {
        return match ($this) {
            self::Standard => 'Everyday business information.',
            self::Confidential => 'Information limited to those who need it.',
            self::Restricted => 'The most sensitive information. Granting this requires you to confirm your identity again.',
        };
    }
}
