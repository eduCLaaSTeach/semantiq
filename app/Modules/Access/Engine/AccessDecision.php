<?php

declare(strict_types=1);

namespace App\Modules\Access\Engine;

use App\Modules\Access\Support\DecisionReason;
use App\Modules\Access\Support\ReasonNarrator;

/**
 * What decide() returns: allowed, why, and ONE deterministically chosen
 * authorising path.
 */
final class AccessDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly DecisionReason $reason,
        public readonly ?GrantPathReference $authorisingPath = null,
    ) {}

    public static function allow(GrantPathReference $path): self
    {
        return new self(true, DecisionReason::AllowedByPath, $path);
    }

    /**
     * An administration allow has no grant path, because the four
     * administration classes never reach grant-path evaluation. The absence is
     * the point rather than an omission.
     */
    public static function allowAdministration(): self
    {
        return new self(true, DecisionReason::AllowedByPath);
    }

    public static function deny(DecisionReason $reason): self
    {
        return new self(false, $reason);
    }

    /** The sentence an administrator reads. Never the reason code. */
    public function explanation(): string
    {
        return ReasonNarrator::narrate($this->reason);
    }
}
