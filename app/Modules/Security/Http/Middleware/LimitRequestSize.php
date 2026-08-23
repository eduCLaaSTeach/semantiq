<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Middleware;

use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Security\Support\SecurityPolicies;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses an oversized request. Feature ADM-011, "bounded payload sizes".
 *
 * The web server and PHP both have their own limits, and they are the ones that
 * actually stop a very large upload reaching this process at all. This is not a
 * replacement for either. It is the APPLICATION saying what it expects, for two
 * reasons the server-level limits cannot cover:
 *
 *  - it is the only one an administrator can see and change from a screen, and
 *    a limit nobody can see is a limit nobody reviews;
 *  - the server limits are set per deployment and drift between environments,
 *    so a payload that a staging server refuses may reach production.
 *
 * 413 rather than 422: the request is not invalid, it is too large, and the
 * distinction matters to whatever is calling.
 */
class LimitRequestSize
{
    public function __construct(
        private readonly SecurityPolicies $policies,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $limitBytes = $this->policies->number('api.max_payload_kilobytes') * 1024;

        /*
         * The declared length, not the parsed body. By the time a body is
         * parsed the memory has already been spent, and the point of a limit is
         * to refuse before that. A request that declares no length - a chunked
         * upload - is passed through to the server's own limit, because this
         * middleware has nothing to measure.
         */
        $declared = $request->headers->get('Content-Length');

        if ($declared !== null && (int) $declared > $limitBytes) {
            $this->audit->denied(
                action: 'security.request.refused',
                module: 'Security',
                resourceType: 'request',
                resourceId: $request->path(),
                reason: 'Request body of '.(int) $declared.' bytes exceeded the configured limit of '.$limitBytes.' bytes.',
            );

            abort(413, 'That request was larger than this application accepts.');
        }

        return $next($request);
    }
}
