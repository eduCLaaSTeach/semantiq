<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Bootstrap;

use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * THE WRITE THE FIRST DRAFT LACKED.
 *
 * The draft said "nothing is disabled by a write - the predicate changes and
 * every guard reads it", and presented that as a virtue. It was the defect:
 * a computed predicate goes back to UNCONFIGURED the moment every System
 * Administrator is deactivated, and with it the original bootstrap password.
 *
 * So the transition writes, and writes BOTH halves:
 *
 *   disabled_at     the state every guard reads (BootstrapAccess)
 *   password_hash   replaced with a value no Hash::check can match
 *
 * BOTH, NOT EITHER. disabled_at alone would leave a working hash behind a flag
 * somebody could clear with one UPDATE; replacing the hash alone would leave
 * the guards reading an open principal. Together, clearing disabled_at by hand
 * afterwards restores NOTHING, because the old hash is gone and there is no
 * path that sets a new one except a recovery token.
 *
 * CALLED INSIDE THE CALLER'S TRANSACTION. close() does NOT open one of its
 * own - it asserts it is already inside one. GrantRedeemer creates the first
 * System Administrator and consumes the grant in a single transaction, and
 * this belongs in the same one: if the role assignment rolls back, bootstrap
 * must not be closed; if closing fails, no administrator may be created. There
 * is deliberately no interval in which both an administrator and an open
 * bootstrap password exist.
 *
 * THE EVIDENCE IS A StateChange, so P1-08's atomicity guard applies and
 * bootstrap.closed commits with the closure or neither does. A closure nobody
 * can prove happened is not much better than one that did not.
 *
 * IDEMPOTENT. Closing an already-closed principal is a no-op rather than an
 * error, because the second close - after a recovery episode ends - is a
 * normal event and not a fault.
 */
final class BootstrapCloser
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    public function close(string $reason): void
    {
        if (DB::transactionLevel() === 0) {
            // A programming error, not a runtime condition. Closing outside a
            // transaction is precisely the window this class exists to remove,
            // so it fails loudly rather than working most of the time.
            throw new \LogicException(
                'BootstrapCloser::close() must run inside the transaction that establishes the administrator.',
            );
        }

        $principal = BootstrapAdministrator::current();

        if ($principal === null || $this->alreadyClosed($principal)) {
            return;
        }

        $principal->disabled_at = now();
        $principal->password_hash = BootstrapAdministrator::UNUSABLE_HASH;
        $principal->save();

        $this->events->record(SecurityEventLogger::BOOTSTRAP_CLOSED, [
            'result' => 'closed',
            'reason' => $reason,
        ]);
    }

    /**
     * "ALREADY CLOSED" MEANS BOTH HALVES, NOT THE FLAG.
     *
     * B14 found this. The first version skipped when disabled_at was set - and
     * during a recovery episode disabled_at IS set, deliberately, because
     * redemption opens the SESSION rather than the principal. So when a
     * restored SSO administrator signed in, the second close did nothing and
     * the recovery password survived: recovery became a mode the deployment
     * stayed in, which is exactly what Correction 3 says it must not be.
     *
     * The honest question is whether a usable credential still exists. A
     * principal carrying a flag and a working hash is not closed, whatever the
     * flag says, because the hash is what lets somebody in.
     */
    private function alreadyClosed(BootstrapAdministrator $principal): bool
    {
        return $principal->isClosed()
            && Hash::info($principal->password_hash)['algoName'] === 'unknown';
    }
}
