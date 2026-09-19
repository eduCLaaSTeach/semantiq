<?php

declare(strict_types=1);

namespace App\Modules\Security\Catalogue;

/**
 * WHAT MUST HAPPEN IF THE EVIDENCE CANNOT BE WRITTEN. D-111.
 *
 * A success and a refusal need OPPOSITE transaction behaviour, and conflating
 * them loses evidence in one direction or invents it in the other.
 */
enum OutcomeClass: string
{
    /**
     * FAIL CLOSED. The evidence joins the caller's transaction, so a failed
     * insert rolls the change back with it. A state change must never complete
     * unevidenced.
     */
    case StateChange = 'state_change';

    /**
     * FAIL CLOSED BY ORDER RATHER THAN BY TRANSACTION.
     *
     * For exactly one thing: a successful sign-in. The state it changes is the
     * SESSION, which is not in the database, so no transaction can cover it and
     * the atomicity guard would be demanding something impossible.
     *
     * The guarantee is the same and the mechanism is the order: the evidence is
     * written FIRST, and the session is only issued once it is. A failure means
     * no session exists to roll back.
     *
     * This is a DECLARATION, not an exemption. It says how the invariant is
     * kept here, and the test that proves it asserts the session rather than
     * the exception - an assertion on the throw passes with the wrong order.
     */
    case StateChangeRecordedFirst = 'state_change_recorded_first';

    /**
     * COMMIT INDEPENDENTLY, and never alter the outcome.
     *
     * A refusal is already not happening: ReviewDecisionService,
     * UserDirectoryService and StepUpService each record a refusal and THEN
     * throw, so evidence that joined the caller's transaction would vanish with
     * the refusal it was recording. A refusal that leaves no trace is precisely
     * the attempt somebody wanted hidden.
     *
     * If the refusal's own evidence cannot be written, the action STAYS
     * REFUSED. Failing closed may turn a success into a failure; it must never
     * turn a refusal into anything else.
     */
    case Refusal = 'refusal';

    /**
     * COMMIT INDEPENDENTLY, and never block.
     *
     * Sign-out and session expiry. Refusing to let somebody sign out because
     * the evidence store is unavailable is a worse outcome than the gap, and
     * the session is gone either way. Log::critical records the gap and is NOT
     * audit evidence.
     */
    case BestEffort = 'best_effort';
}
