<?php

declare(strict_types=1);

namespace App\Modules\Platform\Identity;

/**
 * A verified external identity, and nothing else.
 *
 * Deliberately carries no token, no raw claim set and no refresh material. That
 * is not tidiness - it is why nothing downstream can accidentally persist or log
 * a credential. Code that never receives a token cannot leak one, so the
 * redaction rule in D-12 is enforced by the type rather than by discipline.
 */
final readonly class VerifiedIdentity
{
    public function __construct(
        public string $provider,
        public string $subject,
        public string $tenant,
        public string $email,
        public string $displayName,
    ) {}
}
