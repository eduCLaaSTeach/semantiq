<?php

declare(strict_types=1);

namespace App\Modules\Access\StepUp;

use App\Modules\Platform\Models\User;

/**
 * How a unit outside P1-05 completes its own step-up.
 *
 * GATE C BLOCKER 2. P1-05's StepUpController imported P1-07's models and
 * services directly, which reverses the approved boundary: P1-07 consumes
 * P1-05, never the other way round. A later unit would have added a second
 * import, and the accepted unit would slowly become a switchboard for every
 * unit that came after it.
 *
 * The controller now knows only that SOME actions are not its own, and asks the
 * registry who handles them. It never learns what they mean.
 *
 * The implementation is responsible for re-checking everything: the subject may
 * have moved while the actor was away at Microsoft, and the stored intent is the
 * only thing it may act on.
 */
interface StepUpCompletion
{
    public function handles(StepUpAction $action): bool;

    /**
     * Perform the confirmed action from the STORED row, inside the transaction
     * that consumed the reference.
     *
     * Nothing may be read from the request: the request came back through the
     * browser, and anything in it could have been changed on the way.
     */
    public function complete(PendingStepUp $pending, User $actor): mixed;
}
