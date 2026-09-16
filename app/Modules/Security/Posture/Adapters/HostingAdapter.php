<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture\Adapters;

use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Evidence;
use App\Modules\Security\Posture\PostureState;

/**
 * The three controls that APPLY and that SemantIQ cannot observe.
 *
 * EVERY ONE IS UNVERIFIED, AND NONE IS not_applicable. That distinction is the
 * whole of this file. `not_applicable` means genuinely OUTSIDE Release 1;
 * encryption, web-exposure hardening and backups all plainly apply to this
 * product. What is missing is EVIDENCE, not applicability, and calling an
 * applicable control "not applicable" would quietly write it out of scope -
 * which is a way of getting to green that nobody has to argue for.
 *
 * WEB EXPOSURE IS THE SUBTLE ONE. deploy.yml runs real negative tests against
 * the Apache denial boundary - but AT DEPLOY TIME. Reporting a past deploy as
 * current posture is false-green by construction: the evidence was true about a
 * different moment. D-80.
 *
 * THESE ARE CONSTANTS, deliberately. There is no branch that could turn one
 * green, because there is no evidence that could justify one. A future unit
 * that introduces an accepted runtime source gives the control an adapter.
 * N-SS8a breaks this by reclassifying encryption to NotApplicable, which takes
 * it out of the applicable set and stops it contributing at all.
 */
final class HostingAdapter implements SourceAdapter
{
    public function answers(): array
    {
        return [
            ControlCatalogue::ENCRYPTION,
            ControlCatalogue::WEB_EXPOSURE,
            ControlCatalogue::BACKUPS,
        ];
    }

    public function evidence(): array
    {
        return [
            Evidence::state(
                ControlCatalogue::ENCRYPTION,
                PostureState::Unverified,
                'Encryption is handled by the hosting platform, which SemantIQ cannot see from '
                .'the inside. It applies to this product; SemantIQ simply has no way to confirm '
                .'it, so nothing is claimed either way.',
            ),
            Evidence::state(
                ControlCatalogue::WEB_EXPOSURE,
                PostureState::Unverified,
                'Internal files are checked at the moment of each release, not while the product '
                .'is running. A check that passed at the last release does not describe this '
                .'moment, so nothing is claimed about it now.',
            ),
            Evidence::state(
                ControlCatalogue::BACKUPS,
                PostureState::Unverified,
                'Backups are handled by the hosting platform. It applies to this product; '
                .'SemantIQ has no way to confirm a backup exists or can be restored.',
            ),
        ];
    }
}
