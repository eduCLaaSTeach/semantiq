<?php

declare(strict_types=1);

namespace App\Modules\Governance\Enums;

/**
 * Where a sovereignty exception stands. Feature ADM-016.
 *
 * FOUR STORED STATES, AND EXPIRY IS NOT ONE OF THEM.
 *
 * `Requested`, `Approved`, `Rejected` and `Revoked` each record something a
 * PERSON decided. Whether an approved exception is still in force is a question
 * about today's date, and a date is not a decision.
 *
 * Storing an `expired` state would mean something had to write it - a job, a
 * scheduler, a sweep - and gate 4 introduces none of those (SEC-DEC-069). Worse,
 * it would mean an exception whose sweep had not run yet was still marked
 * approved while its end date had passed, so the record and the reality would
 * disagree for as long as the queue was behind. Deriving it means an exception
 * that lapses at midnight stops applying at midnight, with nothing running.
 *
 * `SovereigntyException::isInForce()` is the one place that combines the stored
 * status with the dates. Nothing compares statuses itself.
 */
enum ExceptionStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Awaiting approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Revoked => 'Revoked',
        };
    }

    /**
     * Whether this status COULD put an exception in force.
     *
     * Deliberately not called `isInForce()`. Only `Approved` can, and even then
     * the dates decide - which is why the model owns that question and this
     * enum does not.
     */
    public function permitsForce(): bool
    {
        return $this === self::Approved;
    }

    /** Whether a decision has been taken at all. */
    public function isDecided(): bool
    {
        return $this !== self::Requested;
    }

    /**
     * The design system's badge class. The whole class, not a fragment.
     *
     * Rejected is NOT rendered as an error. A rejection is the control working:
     * somebody asked to weaken the sovereignty position and was told no.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Requested => 'badge badge-warning',
            self::Approved => 'badge badge-success',
            self::Rejected => 'badge',
            self::Revoked => 'badge badge-violet',
        };
    }
}
