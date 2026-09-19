<?php

declare(strict_types=1);

namespace App\Modules\SystemHealth\Checks;

use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Database\DatabaseManager;
use RuntimeException;
use Throwable;

/**
 * A ROUND TRIP, and a DIFFERENT FACT from the database check.
 *
 * `select 1` proves the connection. It does not prove the session table is
 * present, readable and writable - and a renamed table, or a permissions
 * problem on that one table, leaves the database row green and SIGNS NOBODY
 * IN. That is the outage that looks healthiest, which is why reading
 * config('session.driver') and reporting Available would be worse than having
 * no row at all.
 *
 * DATABASE DRIVER ONLY, AND THAT IS A DECISION RATHER THAN AN OVERSIGHT.
 *
 * A database transaction can only roll back a database write. Against `file`
 * this round trip would leave a session file on disk; against `redis` or
 * `dynamodb`, a live key in the store. The rollback would succeed, roll back
 * nothing, and the screen would report a clean round trip while litter
 * accumulated in the real session store on every render - from the one screen
 * whose entire job is to be trustworthy.
 *
 * None of those stores is deployed, so no adapter is written for one. An
 * unsupported driver is NOT CHECKED, never Available, and the database check is
 * NOT allowed to stand in for it: reporting Available because a different check
 * on a different fact passed is precisely the status nobody measured.
 */
final class SessionStoreCheck
{
    public const SUPPORTED_DRIVER = 'database';

    /** The one identifier prefix this check ever writes. */
    private const SYNTHETIC_PREFIX = 'semantiq-health-';

    public function __construct(private readonly DatabaseManager $db) {}

    /** @return array{status: HealthStatus, explanation: string} */
    public function run(): array
    {
        $driver = (string) config('session.driver');

        if ($driver !== self::SUPPORTED_DRIVER) {
            return [
                'status' => HealthStatus::NotChecked,
                // Names no driver: the row says what was and was not done.
                'explanation' => 'This deployment keeps sign-in records somewhere this check cannot test safely, so it has not been tested here.',
            ];
        }

        try {
            $this->roundTrip();
        } catch (Throwable) {
            // The exception can carry the table name, connection and user.
            return [
                'status' => HealthStatus::Unavailable,
                'explanation' => 'A test sign-in record could not be written and read back, so people may be unable to stay signed in.',
            ];
        }

        return [
            'status' => HealthStatus::Available,
            'explanation' => 'A test sign-in record was written, read back and discarded.',
        ];
    }

    /**
     * INSERT, SELECT, ROLLBACK. No DELETE, no TRUNCATE, no DDL.
     *
     * The rollback is the NORMAL path, not the error path: the closure always
     * throws, so the transaction always unwinds and the synthetic row never
     * exists outside it. D-128 - and the table is never created, altered,
     * dropped or truncated, so MySQL's implicit commit on DDL, which cost P1-08
     * four green-on-SQLite red-on-MySQL cases, cannot be reached from here.
     *
     * The identifier is 32 hex characters of random_bytes behind a fixed
     * prefix, so it collides with no issued session id, and nothing in this
     * method reads, updates or deletes any row but the one it just inserted. A
     * real user's session is never touched.
     */
    private function roundTrip(): void
    {
        $connection = $this->db->connection(config('session.connection'));
        $table = (string) config('session.table', 'sessions');

        $id = self::SYNTHETIC_PREFIX.bin2hex(random_bytes(16));

        try {
            $connection->transaction(function () use ($connection, $table, $id): void {
                $connection->table($table)->insert([
                    'id' => $id,
                    'user_id' => null,
                    'ip_address' => null,
                    'user_agent' => null,
                    'payload' => '',
                    'last_activity' => time(),
                ]);

                $found = $connection->table($table)->where('id', $id)->first();

                if ($found === null || ($found->id ?? null) !== $id) {
                    throw new RuntimeException('written and not read back');
                }

                throw new RollBackTheHealthCheck;
            });
        } catch (RollBackTheHealthCheck) {
            // Success. The write happened, the read agreed, and nothing remains.
        }
    }
}
