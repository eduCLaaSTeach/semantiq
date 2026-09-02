<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

/**
 * A secret is Present or Missing. There is no third thing to say about it.
 *
 * This is an enum rather than a string on purpose. It is the ONE place in the
 * Identity module that reads identity.microsoft.client_secret, and what leaves
 * here cannot be turned back into the value - there is nothing to truncate, no
 * length to report, and no "just the first four characters" available to a
 * later well-meaning change.
 *
 * PHP cannot make reading a config value impossible; any class can call
 * config(). So this does not claim to be a structural guarantee. It narrows the
 * surface to one line, and IdentityArchitectureTest fails if a second line
 * appears.
 */
enum SecretPresence: string
{
    case Present = 'present';

    case Missing = 'missing';

    public static function of(mixed $value): self
    {
        return is_string($value) && $value !== '' ? self::Present : self::Missing;
    }

    /** What a person reads. Never a length, never a fragment. */
    public function inWords(): string
    {
        return $this === self::Present ? 'Present' : 'Missing';
    }
}
