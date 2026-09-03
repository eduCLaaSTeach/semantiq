<?php

declare(strict_types=1);

namespace App\Modules\Domains\Support;

use RuntimeException;

/**
 * A refused Business Domains operation, carrying a stable reason and a message
 * written for an administrator.
 *
 * The same shape as P1-01's StructureViolation and P1-03's PeopleViolation, for
 * the same reason: the screen renders THIS message, never an exception, because
 * rendering an exception is how a stack trace or a database constraint reaches
 * a browser.
 *
 * Every sentence below is a complete instruction. P1-03 shipped a duplicate
 * path that raised a database integrity error at the administrator - found by
 * reading the test script back against the code rather than by a failing test -
 * so each refusal here says what happened AND what to do instead.
 *
 * @property-read list<string> $blockedBy
 */
final class DomainViolation extends RuntimeException
{
    /** @param list<string> $blockedBy */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly array $blockedBy = [],
    ) {
        parent::__construct($message);
    }

    public static function nameTaken(): self
    {
        return new self(
            'name_taken',
            'A domain called that already exists. Open it, or choose another name.'
        );
    }

    public static function codeTaken(): self
    {
        return new self('code_taken', 'That code is already used by another domain.');
    }

    public static function codeReserved(): self
    {
        return new self('code_reserved', 'That code is reserved for a standard domain.');
    }

    public static function ownerRequiredToEnable(): self
    {
        return new self(
            'owner_required',
            'Assign an owner before enabling this domain. Someone has to be accountable for it.'
        );
    }

    public static function ownerInactiveOnEnable(): self
    {
        return new self(
            'owner_inactive',
            'This domain\'s owner is no longer active. Assign an active owner before enabling it.'
        );
    }

    public static function ownerRequiredWhileEnabled(): self
    {
        return new self(
            'owner_required_while_enabled',
            'This domain is enabled. Assign a replacement owner, or disable it first.'
        );
    }

    public static function ownerNotActive(): self
    {
        return new self(
            'owner_not_active',
            'That person\'s account is not active. Choose someone who can sign in.'
        );
    }

    public static function ownerOutsideOrganisation(): self
    {
        return new self('owner_outside_organisation', 'That person is not part of this organisation.');
    }

    public static function baselineNotRemovable(): self
    {
        return new self(
            'baseline_not_removable',
            'Standard domains cannot be removed. Disable it instead.'
        );
    }

    /** @param list<string> $blockedBy */
    public static function inUse(array $blockedBy): self
    {
        return new self(
            'in_use',
            'This domain cannot be removed permanently, because '.self::listOf($blockedBy)
            .'. Disable it instead — that keeps the record of who was accountable for it.',
            $blockedBy
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
