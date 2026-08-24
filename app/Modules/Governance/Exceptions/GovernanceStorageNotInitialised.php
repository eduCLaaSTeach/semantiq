<?php

declare(strict_types=1);

namespace App\Modules\Governance\Exceptions;

use RuntimeException;

/**
 * A governance write was attempted before its table existed.
 *
 * Thrown by the services rather than by the controllers, so a console command,
 * a queued job or a future API meets the same refusal a form does. The
 * controllers catch it and show the banner; nothing swallows it silently.
 *
 * Extends `RuntimeException` deliberately, unlike `SubjectOutsideOrganisation`
 * which extends `DomainException` so it can sail past controller catch blocks.
 * The reasoning is opposite here: a tenancy violation must never be turned into
 * a friendly message, whereas "the migration has not run yet" is precisely a
 * condition a screen should explain calmly and an administrator can fix.
 */
class GovernanceStorageNotInitialised extends RuntimeException
{
    public static function forWrite(string $what): self
    {
        return new self(
            $what.' cannot be saved yet: the database migration for the Data Protection release has '
            .'not been run on this deployment. Nothing was written.'
        );
    }
}
