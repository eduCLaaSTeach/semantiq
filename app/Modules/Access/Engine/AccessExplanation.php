<?php

declare(strict_types=1);

namespace App\Modules\Access\Engine;

use App\Modules\Access\Support\DecisionReason;
use App\Modules\Access\Support\ReasonNarrator;

/**
 * What explain() returns: the SAME allow/deny and the SAME primary reason as
 * decide(), plus every authorising path and the failed candidates.
 *
 * WHY THE SIMULATOR NEEDS ALL PATHS. Manager -> Finance -> Team authorises, and
 * Executive -> Finance -> Organisation also authorises. Revoking the Manager
 * path must not make the simulator imply Finance access disappears when the
 * Executive path still grants it - that is precisely the question an
 * administrator asks before revoking, and a first-match answer would mislead
 * them into a change they did not intend.
 *
 * THE DETAILED MODE NEVER CHANGES THE ANSWER. N-EN1 asserts the same question
 * yields the same allow/deny and the same primary reason in both modes.
 */
final class AccessExplanation
{
    /**
     * @param  list<GrantPathReference>  $authorisingPaths  Deterministically ordered.
     * @param  list<array{reason: DecisionReason, detail: string}>  $failedCandidates
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly DecisionReason $reason,
        public readonly ?GrantPathReference $primaryPath,
        public readonly array $authorisingPaths,
        public readonly array $failedCandidates,
    ) {}

    public function explanation(): string
    {
        return ReasonNarrator::narrate($this->reason);
    }

    /**
     * The decision this explanation contains, so parity is checkable rather
     * than asserted in prose.
     */
    public function toDecision(): AccessDecision
    {
        return new AccessDecision($this->allowed, $this->reason, $this->primaryPath);
    }

    /**
     * Whether removing one path would still leave access. The question an
     * administrator asks before revoking.
     */
    public function remainsAllowedWithout(int $roleAssignmentId): bool
    {
        foreach ($this->authorisingPaths as $path) {
            if ($path->roleAssignmentId !== $roleAssignmentId) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{reason: string, sentence: string, detail: string}> */
    public function narratedFailures(): array
    {
        return array_map(
            static fn (array $failure): array => [
                'reason' => $failure['reason']->value,
                'sentence' => ReasonNarrator::narrate($failure['reason']),
                'detail' => $failure['detail'],
            ],
            $this->failedCandidates,
        );
    }
}
