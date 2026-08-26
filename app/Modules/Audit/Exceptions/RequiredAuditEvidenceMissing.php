<?php

declare(strict_types=1);

namespace App\Modules\Audit\Exceptions;

use RuntimeException;

/**
 * A write that may not proceed without its audit evidence could not record it.
 *
 * WHY THIS EXCEPTION EXISTS AT ALL, given that `AuditLogger::record()`
 * deliberately does NOT throw.
 *
 * Gate 1 settled that an ordinary administrative action must still complete
 * when the audit trail cannot be written. The alternative - an exception that
 * rolls the change back - means a full disk or a locked table can stop somebody
 * disabling a compromised account, and a lost event that is loudly logged is
 * the lesser harm. That reasoning has not changed and `record()` is untouched.
 *
 * It does not hold for a small number of writes whose entire purpose is to BE
 * evidence. A privacy request that records a disclosure as having happened,
 * with no audit event saying it did, is not a slightly worse record - it is an
 * internally inconsistent one. The row asserts a disclosure; the trail denies
 * it; a regulator reading them reaches two opposite conclusions and nobody can
 * say which is true. There, refusing the action is the lesser harm, because the
 * person can be told to try again and nothing has been disclosed in the
 * meantime.
 *
 * So the rule is opt-in and narrow: `recordRequired()` is called by the PDPA-01
 * lifecycle writes and by correction notes, and by nothing else. Broadening it
 * to a module because it feels safer would reintroduce exactly the failure gate
 * 1 refused to accept. SEC-DEC-089.
 *
 * THE MESSAGE IS WRITTEN FOR THE PERSON WHO CLICKED, not for a log reader. It
 * reaches them through the controller's existing `RuntimeException` handling,
 * which returns them to the screen. It extends `RuntimeException` for that
 * reason - a new base class would slip past those five catch blocks.
 */
final class RequiredAuditEvidenceMissing extends RuntimeException
{
    public static function forAction(string $action): self
    {
        return new self(
            'This action was not recorded in the audit trail, so it has been undone and nothing was '
            .'changed. A privacy request must never say something happened without the evidence that it '
            .'did. Try again; if it keeps happening, the audit storage needs attention before this '
            .'request can be progressed. (Unrecorded action: '.$action.'.)'
        );
    }
}
