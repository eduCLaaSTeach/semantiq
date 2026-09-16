<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture\Adapters;

use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Evidence;
use App\Modules\Security\Posture\PostureState;

/**
 * The carried P1-02 provider-wide SSO Re-check. OPEN / CARRIED / UNVERIFIED.
 *
 * It is open because the check needs a second PERMANENT System Administrator to
 * observe safely, and one must not be manufactured. P1-06 does not close it and
 * does not manufacture one.
 *
 * NEVER Healthy - nothing confirmed it. NEVER Critical or Attention - nothing
 * indicates a fault. This is the clearest case in the whole unit for why
 * Unverified has to exist as a first-class state rather than being folded into
 * "fine" or "broken", and N-SS2 is the test that keeps it from being folded.
 *
 * It is a catalogued control rather than a paragraph on a screen so that it
 * appears in Exceptions honestly, gets counted, and cannot be deleted by
 * somebody tidying up copy.
 */
final class CarriedGateAdapter implements SourceAdapter
{
    public function answers(): array
    {
        return [ControlCatalogue::SSO_RECHECK];
    }

    public function evidence(): array
    {
        return [Evidence::state(
            ControlCatalogue::SSO_RECHECK,
            PostureState::Unverified,
            'This check needs a second permanent System Administrator to observe safely. One has '
            .'not been created, deliberately. Nothing is known to be wrong; nothing has been '
            .'confirmed either.',
        )];
    }
}
