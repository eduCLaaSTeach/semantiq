<?php

declare(strict_types=1);

namespace App\Modules\People\Support;

use RuntimeException;

/**
 * A refused People operation, carrying a stable reason and a message written for
 * an administrator.
 *
 * The same shape as P1-01's StructureViolation, and for the same reason: the
 * screen renders THIS message, never an exception. Rendering an exception is how
 * a stack trace, a framework internal or a database constraint reaches a
 * browser.
 *
 * @property-read list<string> $blockedBy
 */
final class PeopleViolation extends RuntimeException
{
    /** @param list<string> $blockedBy */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly array $blockedBy = [],
    ) {
        parent::__construct($message);
    }

    public static function duplicateIdentity(): self
    {
        return new self(
            'duplicate_identity',
            'That person is already in SemantIQ. Open their record instead of adding them again.'
        );
    }

    public static function soleAdministrator(): self
    {
        return new self(
            'sole_administrator',
            'This is the only active System Administrator. Add or retain another active System '
            .'Administrator before deactivating this account.'
        );
    }

    /** @param list<string> $blockedBy */
    public static function organisationChangeBlocked(array $blockedBy): self
    {
        return new self(
            'organisation_change_blocked',
            'This user cannot be moved to another organisation yet, because '
            .self::listOf($blockedBy).'. End those first, then move them.',
            $blockedBy
        );
    }

    /** @param list<string> $blockedBy */
    public static function inUse(string $noun, array $blockedBy): self
    {
        return new self(
            'in_use',
            "This {$noun} cannot be removed permanently, because ".self::listOf($blockedBy).'.',
            $blockedBy
        );
    }

    public static function hasSignedIn(): self
    {
        return new self(
            'has_signed_in',
            'This person has signed in, so their record is kept as part of the organisation\'s '
            .'history. Deactivate them instead.'
        );
    }

    public static function bootstrapAdministrator(): self
    {
        return new self(
            'bootstrap_administrator',
            'This account established the first System Administrator for this deployment and is '
            .'kept as a permanent record. Deactivate it instead.'
        );
    }

    public static function refuse(string $reason, string $message): self
    {
        return new self($reason, $message);
    }

    /** @param list<string> $phrases */
    private static function listOf(array $phrases): string
    {
        if (count($phrases) === 1) {
            return $phrases[0];
        }

        $last = array_pop($phrases);

        return implode(', ', $phrases).' and '.$last;
    }
}
