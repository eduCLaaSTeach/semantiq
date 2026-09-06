<?php

declare(strict_types=1);

namespace App\Modules\Access\Engine;

use App\Modules\Access\Support\ActionClass;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Platform\Models\User;

/**
 * A CLOSED question. Never "what can this person see?".
 *
 * An open-ended query is an invitation to load a permissive set and filter it
 * afterwards, which is the architecture the whole unit forbids. Screens derive
 * their lists from repeated closed questions, or from the explicit §12
 * projection, which is separately reviewed.
 *
 * THIS CARRIES ONLY THE SECURITY QUESTION. There is deliberately no field for
 * evidence mode, verbosity, "explain", "all paths" or anything else about what
 * the caller wants back. A flag inside the question is a flag policy code can
 * read, and it must be structurally impossible for such a flag to alter the
 * answer. decide() and explain() are separate entry points over one evaluator
 * instead - N-EN5 is an architecture guard that fails at this shape.
 *
 * The identity is a PARAMETER, not a session lookup: Phase 2's propagation is
 * not a web request, and an engine reachable only through middleware would
 * force Phase 2 to build a second one.
 */
final class AccessQuestion
{
    public function __construct(
        public readonly ?User $user,
        public readonly string $action,
        public readonly ActionClass $actionClass,
        public readonly ?int $businessDomainId = null,
        public readonly ?ResourceReference $resource = null,
        public readonly Sensitivity $sensitivity = Sensitivity::Standard,
        public readonly ?int $organisationId = null,
    ) {}

    /**
     * An administration question: no domain, no resource, no sensitivity. The
     * four administration classes never reach grant-path evaluation, so those
     * fields would have nothing to say.
     */
    public static function administration(?User $user, string $action, ActionClass $class): self
    {
        return new self($user, $action, $class);
    }

    public static function businessData(
        ?User $user,
        string $action,
        int $businessDomainId,
        ResourceReference $resource,
        Sensitivity $sensitivity,
        ?int $organisationId = null,
    ): self {
        return new self(
            $user,
            $action,
            ActionClass::BusinessData,
            $businessDomainId,
            $resource,
            $sensitivity,
            $organisationId,
        );
    }
}
