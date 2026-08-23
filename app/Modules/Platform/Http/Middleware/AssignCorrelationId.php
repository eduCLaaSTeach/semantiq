<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Audit\Support\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id, and tells the caller what it was.
 *
 * Runs first in the web stack so anything that logs, audits or fails later in
 * the request already has an id to quote. The id is echoed on the response so
 * an administrator seeing an error can read it off the network tab or a support
 * screen and quote it, which is what ADM-024 asks for.
 *
 * An inbound `X-Correlation-Id` is accepted so a chain of calls shares one id,
 * but only when it is a well-formed UUID - see `CorrelationId::start()`. The id
 * is echoed back into logs and pages, so an unvalidated caller-supplied string
 * would be a log-injection foothold.
 */
class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $inbound = $request->headers->get(CorrelationId::HEADER);

        $id = CorrelationId::start(is_string($inbound) ? $inbound : null);

        $response = $next($request);
        $response->headers->set(CorrelationId::HEADER, $id);

        return $response;
    }
}
