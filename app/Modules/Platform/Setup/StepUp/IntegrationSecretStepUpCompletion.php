<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\StepUp;

use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\StepUp\StepUpCompletion;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Secrets\StagedChangeStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * P1-10's half of a confirmed credential change - D-159.
 *
 * ONE STEP-UP AUTHORISES ONE EXACT CHANGE, ONCE. Both halves come off the
 * STORED row and nothing else:
 *
 *   subject_id      the exact staged change, bound when the confirmation began
 *   subject_intent  the exact family, bound at the same moment
 *
 * NOTHING IS READ FROM THE REQUEST. The request came back through the browser
 * and anything in it could have been changed on the way - which for a
 * credential change means the family, the secret name or the value itself.
 *
 * P1-05 NEVER LEARNS WHAT THIS MEANS. It knows only that some actions are not
 * its own and asks the registry who handles them. Importing the Setup module
 * into StepUpController would reverse the boundary that P1-07 established.
 *
 * THE APPLY IS INSIDE THE CONSUMPTION TRANSACTION. StepUpService::consume()
 * opens it, the reference is consumed by a conditional UPDATE, and
 * StagedChangeStore::apply() runs in the same one - so a lost race rolls the
 * credential change back with the confirmation that authorised it.
 *
 * AND THIS METHOD OPENS ITS OWN ANYWAY. Nested, so on the real path it is a
 * savepoint inside consume()'s transaction and changes nothing about the
 * rollback above.
 *
 * It is here because the first version relied entirely on the caller, and
 * D-111's atomicity is not a property a class should hold only while somebody
 * else remembers to. AuditAtomicityTest reads the code rather than running it -
 * it cannot see a transaction two classes away, and a guard that has to be
 * told which callers are trustworthy is the exemption list it was written to
 * avoid. The honest fix was to make the claim true HERE.
 */
final class IntegrationSecretStepUpCompletion implements StepUpCompletion
{
    public const SUBJECT_TYPE = 'staged_integration_change';

    public function __construct(
        private readonly StagedChangeStore $staged,
        private readonly IntegrationConfigurationWriter $writer,
        private readonly SecurityEventLogger $events,
    ) {}

    public function handles(StepUpAction $action): bool
    {
        return in_array($action, [
            StepUpAction::ReplaceIntegrationSecret,
            StepUpAction::RemoveIntegrationSecret,
        ], true);
    }

    public function complete(PendingStepUp $pending, User $actor): RedirectResponse
    {
        if ($pending->subject_type !== self::SUBJECT_TYPE || $pending->subject_id === null) {
            throw AccessViolation::stepUpInvalid();
        }

        $applied = DB::transaction(fn (): ?array => $this->applyAndRecord($pending, $actor));

        if ($applied === null) {
            /*
             * The staged change was already consumed, has expired, or its
             * payload could not be decrypted. Throwing rolls back the step-up
             * consumption with it, so nothing was confirmed and nothing
             * changed - which is the correct direction to fail in for a
             * credential.
             */
            throw AccessViolation::stepUpInvalid();
        }

        return redirect()
            ->route('integrations.show')
            ->with('confirmation', $applied['operation'] === 'remove'
                ? $applied['family']->inWords().' credential removed.'
                : $applied['family']->inWords().' credential replaced.');
    }

    /**
     * The change, its invalidation and its evidence - one transaction.
     *
     * @return array{family: IntegrationFamily, name: string, operation: string}|null
     */
    private function applyAndRecord(PendingStepUp $pending, User $actor): ?array
    {
        $applied = $this->staged->apply((int) $pending->subject_id, (int) $actor->getKey());

        if ($applied === null) {
            return null;
        }

        /*
         * THE EVIDENCE AND THE INVALIDATION, in the same transaction as the
         * change, through the one writer that already does both. A credential
         * that changed without its stored test result being withdrawn is the
         * Correction 5 defect reappearing through the step-up door.
         */
        $this->writer->recordSecretChanged($applied['family'], (int) $actor->getKey());

        $this->events->record(SecurityEventLogger::INTEGRATION_CONFIGURATION_CHANGED, [
            // The FAMILY and the OPERATION. Never the secret name's value, and
            // never a host or endpoint - `provider` is an existing ALLOWED_KEY
            // and carries a configuration choice.
            'provider' => $applied['family']->value,
            'user_id' => (int) $actor->getKey(),
            'result' => $applied['operation'] === 'remove' ? 'removed' : 'changed',
        ]);

        return $applied;
    }
}
