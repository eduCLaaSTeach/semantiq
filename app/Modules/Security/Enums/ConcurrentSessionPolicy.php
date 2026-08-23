<?php

declare(strict_types=1);

namespace App\Modules\Security\Enums;

/**
 * How many places one person may be signed in at once. Feature ADM-010.
 *
 * Every option except `Unlimited` needs the application to enumerate a person's
 * live sessions, which the file session driver cannot do. The screen reports
 * that rather than silently behaving as `Unlimited` - a policy that says
 * "single session" while allowing many is worse than no policy, because
 * somebody will believe it.
 */
enum ConcurrentSessionPolicy: string
{
    case Unlimited = 'unlimited';
    case Single = 'single';
    case Limited = 'limited';

    public function label(): string
    {
        return match ($this) {
            self::Unlimited => 'Unlimited',
            self::Single => 'One at a time',
            self::Limited => 'A set maximum',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::Unlimited => 'A person may be signed in on as many devices as they like.',
            self::Single => 'Signing in on a new device ends the previous session.',
            self::Limited => 'The oldest session ends when the maximum is exceeded.',
        };
    }

    /** Whether enforcing this needs sessions to be enumerable by user. */
    public function requiresSessionEnumeration(): bool
    {
        return $this !== self::Unlimited;
    }
}
