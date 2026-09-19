<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditChainHead;
use App\Modules\Audit\Models\AuditEvent;

/**
 * DETECTION, NOT PREVENTION. D-97, and the distinction is the whole point.
 *
 * Nothing in an application can stop somebody with database or SSH access
 * deleting a row, and on shared cPanel hosting we control no grants. Claiming
 * otherwise would be exactly the unearned assurance CLAUDE.md §6 forbids. What
 * the chain gives is that an alteration or a removal CANNOT GO UNNOTICED - and
 * the screen says so in those words.
 *
 * THREE FINDINGS, DELIBERATELY DISTINGUISHED. "The chain is broken" is not
 * enough for somebody deciding what happened:
 *
 *   altered  the row hashes differently than it did - a field was edited
 *   removed  a row's predecessor hash does not match the row before it
 *   missing  a gap in the sequence
 *
 * A verifier that reported only the first would pass a DELETION, which is the
 * failure this exists to catch.
 */
final class AuditChainVerifier
{
    /**
     * @return array{intact: bool, checked: int, finding: ?string, sequence: ?int}
     */
    public function verify(): array
    {
        $head = AuditChainHead::query()->find(AuditChainHead::ID);

        if ($head === null) {
            return ['intact' => false, 'checked' => 0, 'finding' => 'missing_head', 'sequence' => null];
        }

        $previous = hash('sha256', 'semantiq.audit.genesis|'.$head->started_at->toIso8601String());
        $expected = 1;
        $checked = 0;

        foreach (AuditEvent::query()->orderBy('sequence')->cursor() as $row) {
            $checked++;

            if ($row->sequence !== $expected) {
                return ['intact' => false, 'checked' => $checked, 'finding' => 'missing', 'sequence' => $expected];
            }

            if (! hash_equals($previous, (string) $row->previous_hash)) {
                return ['intact' => false, 'checked' => $checked, 'finding' => 'removed', 'sequence' => $row->sequence];
            }

            // The RAW stored attributes. A cast value would be a different
            // string from the one that was hashed, and every untouched row
            // would read as altered.
            $recomputed = AuditHash::of((string) $row->previous_hash, $row->getAttributes());

            if (! hash_equals($recomputed, (string) $row->row_hash)) {
                return ['intact' => false, 'checked' => $checked, 'finding' => 'altered', 'sequence' => $row->sequence];
            }

            $previous = (string) $row->row_hash;
            $expected++;
        }

        // The head must agree with the last row. A row removed from the END
        // breaks nothing above it, so without this the newest evidence could be
        // dropped silently.
        if ($head->sequence !== $checked) {
            return ['intact' => false, 'checked' => $checked, 'finding' => 'removed', 'sequence' => $head->sequence];
        }

        return ['intact' => true, 'checked' => $checked, 'finding' => null, 'sequence' => null];
    }
}
