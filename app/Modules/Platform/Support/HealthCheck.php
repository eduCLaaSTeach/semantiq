<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Enums\HealthState;

/**
 * The result of one health check, ready to render. Features ADM-001, ADM-024.
 *
 * A read-only value rather than an array so the view cannot be handed a key
 * that was never set, and so the ONE rule this type carries is impossible to
 * forget: `detail` is shown on screen, therefore it must never contain a
 * credential, a connection string, a host name or a customer value. Every
 * producer runs its detail through `Redaction::scrub()` before it gets here.
 *
 * ADM-001's acceptance criteria also ask that a warning LINK to the screen that
 * fixes it. No such link exists yet and none is stubbed here, because a link
 * parameter nothing sets is dead weight a reviewer has to reason about: the
 * screens that would be linked to - authentication policy, the data protection
 * profile, the sovereignty profile, the integration registry - are built in
 * gates 3 to 5 of this release. The field arrives with the first real target.
 * doc/execution/ADMIN-FOUNDATION-RELEASE-1-PLAN.md records this as deferred
 * rather than done.
 */
readonly class HealthCheck
{
    public function __construct(
        public string $name,
        public HealthState $state,
        public string $detail,
    ) {}

    /**
     * Whether this check is asking somebody to do something.
     */
    public function needsAttention(): bool
    {
        return $this->state !== HealthState::Healthy;
    }
}
