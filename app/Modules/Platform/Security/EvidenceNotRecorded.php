<?php

declare(strict_types=1);

namespace App\Modules\Platform\Security;

use RuntimeException;

/**
 * Evidence could not be written, so the thing it evidences did not happen.
 * D-111.
 *
 * IT LIVES IN PLATFORM, BESIDE THE INTERFACE THAT RAISES IT, and not in the
 * Audit module. Callers that must react to a failed write - the sign-in
 * callback most of all - would otherwise have to name Modules\Audit, and a
 * later unit reached backwards into by an earlier one is the boundary reversal
 * P1-07's Gate C caught once already. The seam is Platform's; so is its
 * failure.
 *
 * ONE SENTENCE, and it says what to do next. "Nothing has been changed" is the
 * part that matters: an administrator told only that an action failed, and not
 * whether it half-happened, will try it again.
 */
final class EvidenceNotRecorded extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function make(): self
    {
        return new self(
            'evidence_not_recorded',
            'This action was not completed, because it could not be recorded. Nothing has been changed.'
        );
    }
}
