<?php

declare(strict_types=1);

namespace App\Modules\Security\Projection;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Models\User;

/**
 * Who is looking, and whether platform VALUES are theirs to see.
 *
 * ASKED OF THE ONE ENGINE. There is exactly one definition of "is this person a
 * System Administrator" in the codebase, and a second helper here is precisely
 * what N-B8 already breaks in P1-05. This asks holdsRole() and nothing else.
 *
 * Reaching Security Status at all is decided by RequireActionClass with
 * EvidenceRead, which System Administrator, Organisation Administrator and
 * Auditor all hold. This class answers a different and narrower question: of
 * the people who may read the evidence, who may read the PLATFORM evidence.
 * Only a System Administrator, who also holds PlatformAdmin. Identity
 * configuration authority stays System-Administrator-only, per P1-05.
 */
final class Viewer
{
    private function __construct(public readonly bool $seesPlatformValues) {}

    public static function for(?User $user, AccessEngine $engine): self
    {
        if (! $user instanceof User) {
            // No user, no platform values. Fail closed - and the route's own
            // RequireActionClass would already have refused the request.
            return new self(false);
        }

        return new self($engine->holdsRole($user, RoleCode::SystemAdministrator));
    }

    /** For fixtures and tests only. */
    public static function withPlatformValues(bool $sees): self
    {
        return new self($sees);
    }
}
