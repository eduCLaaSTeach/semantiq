<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use DomainException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * A service was asked to act on a record belonging to another organisation.
 *
 * The tenancy boundary, thrown from the identity services rather than from a
 * controller, because `users` deliberately carries no global organisation scope
 * - it is the authentication table, and a fail-closed global scope there would
 * mean nobody can sign in when the context fails to resolve (SEC-DEC-022). That
 * choice moves the burden onto every path that MUTATES an account, and this
 * exception is how that burden is discharged in one place.
 *
 * WHY IT EXTENDS `DomainException` AND NOT `RuntimeException`, which is the
 * single most important line in this file.
 *
 * Every controller in this module already catches `InvalidArgumentException`
 * and `RuntimeException` and turns them into a message on the form - that is
 * how a refused elevation or a last-administrator refusal reaches the
 * administrator. `DomainException` extends `LogicException`, so it is caught by
 * NEITHER, and it sails past those handlers untouched to Laravel's own.
 *
 * That is deliberate and it is what makes this fail closed. A controller cannot
 * accidentally swallow a tenancy violation into a friendly form error, and a
 * controller added later by somebody who never read this file still gets the
 * right behaviour without having to remember anything. The guard cannot be
 * defeated by forgetting a catch block.
 *
 * IT RENDERS AS 404, NOT 403, following the convention already set by
 * `UserController::authorizeSubject()`. A 403 confirms that the id exists and
 * belongs to somebody; a 404 says only that this organisation has no such
 * record, which is the truth from where the caller is standing and tells an
 * attacker probing ids nothing at all. The difference matters because the ids
 * are sequential integers.
 *
 * The message is never shown. It exists for the log and for a developer reading
 * a stack trace, and it deliberately names no detail about the record.
 *
 * `HttpExceptionInterface` is what tells Laravel's handler to render it as a
 * 404 using the framework's own error page. Implementing the interface rather
 * than a `render()` method keeps the class free of any view dependency, so a
 * console command or a queued job that hits this gets a plain exception and a
 * web request gets a 404 - from one declaration, with no per-caller code.
 */
class SubjectOutsideOrganisation extends DomainException implements HttpExceptionInterface
{
    /**
     * Name the operation that was refused, for the exception message.
     *
     * The organisation and the record are NOT named. This message can reach a
     * log, and a log line saying which customer owns which id is a small
     * cross-tenant leak of its own.
     */
    public static function for(string $operation): self
    {
        return new self(
            'Refused "'.$operation.'": the record belongs to a different organisation. '
            .'Cross-organisation access is denied by default.'
        );
    }

    public function getStatusCode(): int
    {
        return 404;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
