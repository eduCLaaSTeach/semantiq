<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Bootstrap;

use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapRecoveryToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * THE ONLY THING THAT REOPENS LOCAL PASSWORD LOGIN once bootstrap has closed.
 *
 * Correction 3 made UNCONFIGURED necessary and not sufficient. This is what
 * supplies the rest, and it is deliberately hard to reach: a trusted operator
 * at an SSH prompt, once, with a record.
 *
 * THE TOKEN PATTERN IS BootstrapGrant'S, WHICH P1-00 VERIFIED. High entropy,
 * SHA-256 at rest, a TTL, and single-use consumption whose guard is in the
 * WHERE clause of a conditional UPDATE - so two concurrent redemptions cannot
 * both succeed, because the database decides and not the application's timing.
 *
 * REDEMPTION OPENS A SESSION, NOT THE PRINCIPAL. It sets the recovery flag on
 * THIS session and sets a fresh usable credential; it does NOT clear
 * disabled_at. Clearing it would make a single consumed token leave the
 * deployment permanently open - recovery as a mode rather than an episode -
 * and the next person to look would find an enabled local password with
 * nothing to explain it.
 *
 * RECOVERY CLOSES AGAIN BY THE SAME WRITE AS THE FIRST TRANSITION. A restored
 * SSO administrator signing in runs through GrantRedeemer, which calls
 * BootstrapCloser, which replaces the recovery credential with an unusable
 * hash. There is one closing mechanism, used twice, rather than two.
 */
final class BootstrapRecovery
{
    private const TTL_MINUTES = 30;

    public function __construct(private readonly SecurityEventLogger $events) {}

    /**
     * Issue a token. The plaintext is returned ONCE and never stored.
     *
     * The caller is the SSH command, which prints it to the operator's
     * terminal. Nothing writes it to the database, a log, an audit context or
     * CI output - only its SHA-256 is kept.
     */
    public function issue(?string $issuedBy = null): string
    {
        $token = Str::random(64);

        /*
         * ONE TRANSACTION, because this is a state change with evidence.
         *
         * P1-08's atomicity guard caught the first version of this method
         * writing the token row and recording bootstrap.recovery.issued outside
         * any transaction. The consequence is not theoretical: a token that
         * exists with no record of who issued it is a credential that reopens
         * the local password with nothing to audit - which is the one property
         * this whole mechanism is supposed to have.
         *
         * The expiry is computed ONCE and used twice. The first version called
         * now()->addMinutes() separately for the row and the evidence, so the
         * recorded expiry could differ from the stored one by however long the
         * insert took.
         */
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        DB::transaction(function () use ($token, $expiresAt, $issuedBy): void {
            BootstrapRecoveryToken::query()->create([
                'token_hash' => BootstrapRecoveryToken::hashFor($token),
                'expires_at' => $expiresAt,
                'issued_by' => $issuedBy,
            ]);

            $this->events->record(SecurityEventLogger::BOOTSTRAP_RECOVERY_ISSUED, [
                'result' => 'issued',
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
        });

        return $token;
    }

    /**
     * Redeem a token: open the recovery context on this session and set a new
     * local credential.
     *
     * @return bool False is the only refusal shape - unknown, expired and
     *              already-consumed are indistinguishable, for the same reason
     *              the sign-in refusal is generic.
     */
    public function redeem(Request $request, string $token, string $newPassword): bool
    {
        $record = BootstrapRecoveryToken::query()
            ->where('token_hash', BootstrapRecoveryToken::hashFor($token))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($record === null) {
            return false;
        }

        $principal = BootstrapAdministrator::current();

        if ($principal === null) {
            return false;
        }

        $consumedInTransaction = DB::transaction(function () use ($record, $principal, $newPassword): bool {
            // THE GUARD IS IN THE WHERE CLAUSE. Exactly one row must be
            // affected; zero means another request won the race, and the new
            // credential below rolls back with it.
            $consumed = BootstrapRecoveryToken::query()
                ->whereKey($record->getKey())
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->update(['consumed_at' => now()]);

            if ($consumed !== 1) {
                return false;
            }

            $principal->password_hash = Hash::make($newPassword);
            $principal->save();

            $this->events->record(SecurityEventLogger::BOOTSTRAP_RECOVERY_CONSUMED, [
                'result' => 'consumed',
            ]);

            return true;
        });

        if (! $consumedInTransaction) {
            return false;
        }

        /*
         * THE CONTEXT IS WRITTEN, AND ONLY AFTER THE TOKEN IS DEFINITELY
         * CONSUMED.
         *
         * disabled_at is deliberately NOT cleared. The principal stays closed;
         * what opens is this session, which is why recovery is an episode with
         * an end rather than a state the deployment can be left in.
         */
        $request->session()->put(BootstrapAccess::RECOVERY_SESSION_KEY, true);

        return true;
    }
}
