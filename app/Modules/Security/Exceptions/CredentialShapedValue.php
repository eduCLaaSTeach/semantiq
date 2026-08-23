<?php

declare(strict_types=1);

namespace App\Modules\Security\Exceptions;

use DomainException;

/**
 * Thrown when something that looks like a credential was about to be stored.
 *
 * Extends `DomainException` for the same reason
 * `Identity\Exceptions\SubjectOutsideOrganisation` does (SEC-DEC-034):
 * controllers here catch `RuntimeException` to turn a refusal into a form
 * message, and a credential about to be written to the database must sail past
 * a forgotten catch block rather than be swallowed into a friendly error.
 *
 * THE MESSAGE NEVER QUOTES THE VALUE. Naming the field is enough for the person
 * to fix it, and echoing the offending string would write the credential into
 * an exception message, a log line and possibly an error page - which is the
 * exact outcome the check exists to prevent.
 */
class CredentialShapedValue extends DomainException
{
    public static function in(string $field): self
    {
        return new self(sprintf(
            'The value given for "%s" looks like a credential. Secret references record WHERE a credential is '
            .'kept, never the credential itself. Enter a name, path or identifier that points at it instead.',
            str_replace('_', ' ', $field),
        ));
    }
}
