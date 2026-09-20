<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Secrets;

use App\Modules\Platform\Setup\IntegrationFamily;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stages a credential change, and applies it once - D-159.
 *
 * THE TWO-STAGE PATTERN, and why it is two stages rather than one.
 *
 * Replacing a stored credential requires step-up, and step-up means leaving for
 * Microsoft and coming back. The new secret has to survive that round trip
 * somewhere:
 *
 *   in the session    the session store on this deployment is a DATABASE TABLE,
 *                     so this would put a plaintext credential in a row that is
 *                     not the credential store, with none of its protections;
 *   in the URL        no;
 *   in pending_step_ups   that is P1-05's privileged-action table, whose columns
 *                     are structural and safe to read. A secret there makes
 *                     every listing of pending confirmations one careless
 *                     select away from rendering one;
 *   asked again after the round trip
 *                     means typing a credential twice, and makes the
 *                     confirmation screen a second place worth phishing.
 *
 * So it is staged HERE: encrypted, short-lived, single-use, in a table whose
 * only job is this.
 *
 * NOTHING IS APPLIED UNTIL THE STEP-UP IS CONSUMED. apply() runs inside the
 * caller's transaction - the same one that consumes the step-up reference - so
 * a refused, expired or replayed confirmation leaves the configuration exactly
 * as it was.
 */
final class StagedChangeStore
{
    /**
     * The same lifetime as the step-up itself.
     *
     * Deliberately not longer: a staged credential that outlives the
     * confirmation it belongs to is a credential sitting in a table for no
     * reason anybody could state.
     */
    private const LIFETIME_MINUTES = 10;

    public function __construct(private readonly IntegrationSecretStore $secrets) {}

    /**
     * Stage a replacement. Returns the row id, which is what travels in the
     * step-up record.
     */
    public function stageReplacement(
        IntegrationFamily $family,
        string $name,
        string $value,
        int $actorId,
    ): int {
        $this->assertSecretIsAllowed($family, $name);

        return (int) StagedIntegrationChange::query()->create([
            'family' => $family->value,
            'secret_name' => $name,
            'operation' => StagedIntegrationChange::OPERATION_REPLACE,
            'ciphertext' => Crypt::encryptString($value),
            'requested_by_user_id' => $actorId,
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ])->getKey();
    }

    /** Stage a removal. No payload: there is nothing to carry. */
    public function stageRemoval(IntegrationFamily $family, string $name, int $actorId): int
    {
        $this->assertSecretIsAllowed($family, $name);

        return (int) StagedIntegrationChange::query()->create([
            'family' => $family->value,
            'secret_name' => $name,
            'operation' => StagedIntegrationChange::OPERATION_REMOVE,
            'ciphertext' => null,
            'requested_by_user_id' => $actorId,
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ])->getKey();
    }

    /**
     * Consume the staged change and apply it. MUST run inside the caller's
     * transaction.
     *
     * THE GUARD IS IN THE WHERE CLAUSE. Exactly one row must be affected; zero
     * means it was consumed, expired or never existed, and the caller's
     * transaction - including the step-up consumption - rolls back with it.
     *
     * @return array{family: IntegrationFamily, name: string, operation: string}|null
     */
    public function apply(int $stagedId, ?int $actorId = null): ?array
    {
        if (DB::transactionLevel() === 0) {
            // Applying outside a transaction would let the credential change
            // commit while the step-up consumption that authorised it rolled
            // back - a change nobody confirmed.
            throw new \LogicException(
                'StagedChangeStore::apply() must run inside the transaction that consumes the step-up.',
            );
        }

        $staged = StagedIntegrationChange::query()->whereKey($stagedId)->first();

        if ($staged === null) {
            return null;
        }

        $consumed = StagedIntegrationChange::query()
            ->whereKey($stagedId)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        if ($consumed !== 1) {
            return null;
        }

        $family = IntegrationFamily::from($staged->family);

        if ($staged->operation === StagedIntegrationChange::OPERATION_REMOVE) {
            $this->secrets->forget($family->value, $staged->secret_name);
        } elseif (! is_string($staged->ciphertext) || $staged->ciphertext === '') {
            // A staged replacement with no payload is not a replacement.
            return null;
        } else {
            /*
             * THE CIPHERTEXT IS HANDED OVER, NOT OPENED HERE.
             *
             * The first version decrypted the column in this class and passed
             * the plaintext to put(). It worked, and it made a second place in
             * the application where a secret becomes readable -
             * SecretsAreDecryptedInOnePlace failed on it, correctly. The store
             * that owns encryption owns this too.
             */
            $adopted = $this->secrets->adoptStaged(
                $family->value,
                $staged->secret_name,
                $staged->ciphertext,
                $actorId,
            );

            if (! $adopted) {
                // Undecryptable. Rolling the caller back is the right
                // direction rather than writing an empty credential over a
                // working one.
                return null;
            }
        }

        /*
         * THE PAYLOAD IS DESTROYED THE MOMENT IT HAS BEEN USED.
         *
         * consumed_at alone would leave the ciphertext sitting in the table
         * indefinitely - still decryptable by anyone who can read the row and
         * the key. Nulling it means the window in which a used credential
         * exists in two places is the length of one transaction.
         */
        $staged->ciphertext = null;
        $staged->save();

        return [
            'family' => $family,
            'name' => $staged->secret_name,
            'operation' => $staged->operation,
        ];
    }

    private function assertSecretIsAllowed(IntegrationFamily $family, string $name): void
    {
        if (! in_array($name, $family->secrets(), true)) {
            throw new InvalidArgumentException(
                sprintf('[%s] has no secret named [%s].', $family->value, $name),
            );
        }
    }
}
