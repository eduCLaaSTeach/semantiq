<?php

declare(strict_types=1);

namespace App\Modules\SystemHealth\Checks;

use RuntimeException;

/**
 * The private signal that unwinds the session round trip's transaction.
 *
 * A SEPARATE CLASS, not a bare RuntimeException, and the difference is what
 * keeps the check honest: SessionStoreCheck catches THIS type and nothing else,
 * so a genuine failure - a missing table, a permissions error, a row that comes
 * back wrong - propagates and is reported as Unavailable. Catching a general
 * exception to unwind the transaction would have swallowed the outage this
 * check exists to find.
 *
 * It never escapes the check and is never rendered.
 */
final class RollBackTheHealthCheck extends RuntimeException {}
