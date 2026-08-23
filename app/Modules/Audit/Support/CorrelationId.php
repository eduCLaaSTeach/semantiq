<?php

declare(strict_types=1);

namespace App\Modules\Audit\Support;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * The identifier that ties one request to everything it caused.
 *
 * Release 1 section 3 requires a correlation id on every outbound integration
 * call, and ADM-024 requires diagnostics to surface recent error correlation
 * ids so an administrator can be given something to quote without being given
 * anything sensitive. The same id therefore has to reach the audit trail, the
 * log lines and, from gate 5, the outbound HTTP headers.
 *
 * Held in Laravel's request `Context` rather than in a static property, so it
 * survives into queued jobs the request dispatches and does not leak between
 * requests in a long-lived worker.
 *
 * It is a random UUID and carries NO information: not the account, not the
 * address, not the tenant. It is safe to show on screen and safe to put in an
 * email to support, which is the whole point of having one.
 */
class CorrelationId
{
    /** The context key, and the response header it is echoed on. */
    public const KEY = 'correlation_id';

    public const HEADER = 'X-Correlation-Id';

    /**
     * The id in force, generating one if this is the first ask.
     */
    public static function current(): string
    {
        $existing = Context::get(self::KEY);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        return self::start();
    }

    /**
     * Begin a new correlated unit of work and return its id.
     */
    public static function start(?string $id = null): string
    {
        /*
         * An inbound value is never trusted as-is. A caller-supplied id is
         * echoed into logs and screens, so an unbounded or non-printable one
         * would be a log-injection foothold; anything that is not a plain UUID
         * is replaced rather than sanitised, because there is no cost to
         * replacing it.
         */
        $id = $id !== null && Str::isUuid($id) ? $id : (string) Str::uuid();

        Context::add(self::KEY, $id);

        return $id;
    }
}
