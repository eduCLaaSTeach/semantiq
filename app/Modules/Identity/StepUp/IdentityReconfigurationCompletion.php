<?php

declare(strict_types=1);

namespace App\Modules\Identity\StepUp;

use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\StepUp\StepUpCompletion;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Identity\Configuration\IdentityReconfiguration;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Setup\Secrets\StagedIntegrationChange;
use Illuminate\Http\RedirectResponse;

/**
 * P1-02's half of a confirmed sign-in change - Gate C round 3.
 *
 * IT VERIFIES BEFORE IT WRITES, which is the whole reason this is a separate
 * completion rather than a reuse of the integration one. Every other confirmed
 * change in SemantIQ applies on confirmation. This one cannot: applying an
 * unverified directory would leave nobody able to sign in to the deployment
 * that holds it, including the administrator who just confirmed.
 *
 * NOTHING IS READ FROM THE REQUEST. The staged row's id and the family come off
 * the STORED pending row, bound when the confirmation began, through the
 * subject_type / subject_id seam P1-07 established. The request came back
 * through a browser and anything in it could have been changed on the way -
 * which here would mean changing which directory the deployment trusts.
 *
 * A REFUSED CANDIDATE IS NOT AN ERROR PAGE. The administrator typed something
 * Microsoft did not recognise; they are returned to the screen with the probe's
 * own chosen sentence and the working configuration still in force.
 */
final class IdentityReconfigurationCompletion implements StepUpCompletion
{
    public const SUBJECT_TYPE = 'identity_reconfiguration';

    public function __construct(private readonly IdentityReconfiguration $reconfiguration) {}

    public function handles(StepUpAction $action): bool
    {
        return $action === StepUpAction::ReconfigureIdentity;
    }

    public function complete(PendingStepUp $pending, User $actor): RedirectResponse
    {
        if ($pending->subject_type !== self::SUBJECT_TYPE || $pending->subject_id === null) {
            throw AccessViolation::stepUpInvalid();
        }

        $staged = StagedIntegrationChange::query()
            ->whereKey((int) $pending->subject_id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($staged === null) {
            // Already used, or expired while the administrator was away.
            // Nothing was changed, and saying so is the honest answer.
            return redirect()
                ->route('identity.entra')
                ->with('refusal', 'That change had already been used or had expired. Microsoft sign-in has not been changed.');
        }

        $result = $this->reconfiguration->activate($staged, (int) $actor->getKey());

        if (! $result['activated']) {
            /*
             * THE WORKING CONFIGURATION IS STILL IN FORCE. The probe's own
             * sentence is shown - it is one of ProviderProbe's declared
             * strings, never a caught provider error body, which routinely
             * echoes endpoints and identifiers.
             */
            return redirect()
                ->route('identity.entra')
                ->with('refusal', $result['explanation'].' Microsoft sign-in has not been changed.');
        }

        return redirect()
            ->route('identity.entra')
            ->with('confirmation', 'Microsoft sign-in has been updated and checked.');
    }
}
