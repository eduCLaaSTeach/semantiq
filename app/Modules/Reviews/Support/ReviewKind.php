<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Support;

/**
 * The two reviewable objects. D-84.
 *
 * They are different things, not two filters over one thing: platform privilege
 * has no entitlement beneath it, so the role assignment IS the access, while
 * business access is a domain entitlement with a composition beneath it.
 */
enum ReviewKind: string
{
    case Privileged = 'privileged';
    case Domain = 'domain';

    public function label(): string
    {
        return match ($this) {
            self::Privileged => 'Privileged access',
            self::Domain => 'Domain access',
        };
    }
}
