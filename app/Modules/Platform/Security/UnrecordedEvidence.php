<?php

declare(strict_types=1);

namespace App\Modules\Platform\Security;

/**
 * An EvidenceRecorder that stores nothing.
 *
 * IT EXISTS FOR EXACTLY ONE REASON: a test double that constructs
 * SecurityEventLogger directly needs something to pass, and a NULLABLE
 * dependency would let the real application forget one silently. Here the
 * choice to store nothing has to be made out loud.
 *
 * AuditWiringTest asserts the CONTAINER binds the real recorder, so this class
 * can never become the running configuration by accident - which is precisely
 * what a convenient no-op otherwise becomes.
 */
final class UnrecordedEvidence implements EvidenceRecorder
{
    public function record(string $event, array $context): void
    {
        // Deliberately nothing.
    }
}
