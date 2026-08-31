<?php

declare(strict_types=1);

namespace App\Modules\Platform\Security;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * The D-12 boundary: structured, redacted security events through the existing
 * logging boundary. No audit table - P1-08 owns durable storage and adopts
 * these events later.
 *
 * The context shape is fixed rather than a free array. That is the whole point:
 * a caller cannot pass a token, code, nonce or grant by accident, because there
 * is nowhere for it to go. A forbidden key is a hard failure, not a warning,
 * because a security logger that quietly drops a leak is worse than none.
 */
final class SecurityEventLogger
{
    public const BOOTSTRAP_GRANT_ISSUED = 'bootstrap.grant.issued';

    public const BOOTSTRAP_COMPLETED = 'bootstrap.completed';

    public const BOOTSTRAP_REFUSED = 'bootstrap.refused';

    public const LOGIN_SUCCEEDED = 'auth.login.succeeded';

    public const LOGIN_REFUSED_UNKNOWN = 'auth.login.refused.unknown_identity';

    public const LOGIN_REFUSED_INACTIVE = 'auth.login.refused.inactive';

    public const LOGIN_REFUSED_TENANT = 'auth.login.refused.tenant';

    public const LOGIN_REFUSED_PROTOCOL = 'auth.login.refused.protocol';

    public const LOGOUT = 'auth.logout';

    public const SESSION_EXPIRED = 'auth.session.expired';

    private const EVENTS = [
        self::BOOTSTRAP_GRANT_ISSUED,
        self::BOOTSTRAP_COMPLETED,
        self::BOOTSTRAP_REFUSED,
        self::LOGIN_SUCCEEDED,
        self::LOGIN_REFUSED_UNKNOWN,
        self::LOGIN_REFUSED_INACTIVE,
        self::LOGIN_REFUSED_TENANT,
        self::LOGIN_REFUSED_PROTOCOL,
        self::LOGOUT,
        self::SESSION_EXPIRED,
    ];

    /** Only these keys may ever appear in an event's context. */
    private const ALLOWED_KEYS = ['provider', 'subject', 'tenant', 'user_id', 'result', 'reason', 'expires_at'];

    /**
     * @param  array<string, scalar|null>  $context
     */
    public function record(string $event, array $context = []): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            throw new InvalidArgumentException("Unknown security event [{$event}].");
        }

        foreach (array_keys($context) as $key) {
            if (! in_array($key, self::ALLOWED_KEYS, true)) {
                throw new InvalidArgumentException(
                    "Security event context key [{$key}] is not permitted. Tokens, codes, "
                    .'nonces, PKCE verifiers and bootstrap grants must never be logged.'
                );
            }
        }

        Log::info($event, $context + ['at' => now()->toIso8601String()]);
    }

    /**
     * @return list<string>
     */
    public static function events(): array
    {
        return self::EVENTS;
    }
}
