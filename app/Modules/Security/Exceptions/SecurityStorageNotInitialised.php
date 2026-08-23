<?php

declare(strict_types=1);

namespace App\Modules\Security\Exceptions;

use RuntimeException;

/**
 * Thrown when a security write is attempted before the gate 3 migration has run.
 *
 * WRITES FAIL CLOSED, and that is the point of having a separate exception
 * rather than reusing the read fallback. A read can safely resolve the
 * catalogue default, because the default IS what is in force when no override
 * can exist. A write cannot: accepting one and discarding it would tell an
 * administrator their security policy had changed when nothing had, which is
 * worse than any error message.
 *
 * The message names the cause and the fix. It quotes no SQL, no table name that
 * matters and no driver text: a raw database exception reaching a browser is
 * both an information leak and useless to the person reading it.
 */
class SecurityStorageNotInitialised extends RuntimeException
{
    public static function forWrite(string $what): self
    {
        return new self(
            $what.' cannot be saved yet. Security storage has not been initialised on this deployment: the database '
            .'migration for the Security release has not been run. Nothing has been changed. An administrator with '
            .'server access needs to run the outstanding migrations, after which this will save normally.'
        );
    }
}
