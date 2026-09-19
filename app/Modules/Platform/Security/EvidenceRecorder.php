<?php

declare(strict_types=1);

namespace App\Modules\Platform\Security;

/**
 * THE SEAM P1-08 PERSISTS THROUGH. D-95.
 *
 * SecurityEventLogger stays the single emit boundary and gains no knowledge of
 * Audit: it validates, hands the validated event here, and logs. The
 * implementation lives in P1-08 and reads the catalogue's declared semantics.
 *
 * WHY AN INTERFACE IN PLATFORM RATHER THAN A CALL INTO Modules\Audit. Platform
 * is what every later unit depends on; a direct call would reverse the
 * boundary and make P1-00 depend on P1-08. The interface is Platform's, the
 * implementation is Audit's, and the direction stays right.
 */
interface EvidenceRecorder
{
    /**
     * Persist the validated event as durable evidence.
     *
     * @param  array<string, scalar|null>  $context  already validated against ALLOWED_KEYS
     *
     * @throws EvidenceNotRecorded when a state change cannot be evidenced (D-111)
     */
    public function record(string $event, array $context): void;
}
