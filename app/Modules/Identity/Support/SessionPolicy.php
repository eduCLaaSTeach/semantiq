<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;

/**
 * The session policy, in two halves that must agree - D-31.
 *
 * The defect this class exists because of: EnsureSessionIsCurrent declared
 * IDLE_MINUTES = 60 and nothing ever read it. The idle timeout actually enforced
 * was Laravel's own session.lifetime, which production set to 120. So the
 * approved policy said one thing, the running system did another, and no test
 * could catch it because the constant nobody read could not be wrong about
 * anything.
 *
 * The fix is not another constant. It is the distinction below:
 *
 *   ENFORCED  read from whatever actually enforces it. Never a literal here.
 *   APPROVED  the policy those values are checked AGAINST. A literal, once.
 *
 * The Session Policy screen displays the ENFORCED values and never the approved
 * ones, so it cannot show a number the system is not applying. The health check
 * compares the two, so a production .env that drifts turns the screen amber
 * instead of lying quietly.
 *
 * On the obvious objection - is APPROVED_IDLE_MINUTES just IDLE_MINUTES wearing
 * a new name? The original was dead: grep found its declaration and nothing
 * else, and deleting it would have changed no behaviour and failed no test.
 * This one is read by the health check and by SessionPolicyDriftTest, and both
 * fail loudly when it disagrees with what is enforced. A constant nothing reads
 * is how the defect happened; a constant two guards read is what catches it.
 */
final class SessionPolicy
{
    /** P1-00 D-10, reaffirmed by D-31. */
    public const APPROVED_IDLE_MINUTES = 60;

    /** P1-00 D-10, unchanged by D-31. */
    public const APPROVED_ABSOLUTE_HOURS = 12;

    /** What Laravel will actually expire an idle session after. */
    public function idleMinutes(): int
    {
        return (int) config('session.lifetime');
    }

    /** What the middleware will actually end a session at, however active. */
    public function absoluteHours(): int
    {
        return EnsureSessionIsCurrent::ABSOLUTE_HOURS;
    }

    public function driver(): string
    {
        return (string) config('session.driver');
    }

    /**
     * D-10, and not a setting. There is no configuration key for this and none
     * is added: the middleware re-reads the user on every protected request,
     * uncached, and the only way to change that is to change the middleware.
     */
    public function revalidatesEveryRequest(): bool
    {
        return true;
    }

    public function matchesApprovedPolicy(): bool
    {
        return $this->idleMinutes() === self::APPROVED_IDLE_MINUTES
            && $this->absoluteHours() === self::APPROVED_ABSOLUTE_HOURS;
    }

    /** An idle timeout at or beyond the absolute lifetime makes the absolute one decorative. */
    public function idleIsShorterThanAbsolute(): bool
    {
        return $this->idleMinutes() < $this->absoluteHours() * 60;
    }

    /** How the driver is named to a person, rather than to a config file. */
    public function driverInWords(): string
    {
        return match ($this->driver()) {
            'database' => 'Database',
            'file' => 'Server files',
            'redis' => 'Redis',
            'cookie' => 'Browser cookie',
            'array' => 'Memory (not persisted)',
            default => 'Other',
        };
    }
}
