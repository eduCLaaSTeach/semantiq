<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Middleware;

use App\Modules\Security\Support\SecurityPolicies;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The security headers on every response. Feature ADM-011.
 *
 * ADM-011 lists "secure headers" among its required controls, and before this
 * gate the application sent none of them. That was the finding that turned
 * ADM-011 from a settings screen into a screen with something to report.
 *
 * WHAT EACH HEADER IS FOR, because a list of header names is not a rationale:
 *
 *   X-Content-Type-Options: nosniff
 *     Stops a browser guessing that an uploaded file is really a script.
 *
 *   X-Frame-Options / frame-ancestors
 *     Stops this application being framed by another site, which is how a
 *     clickjack turns an administrator's click into a privileged action.
 *
 *   Referrer-Policy: strict-origin-when-cross-origin
 *     Stops a URL leaking outward. SemantIQ URLs carry record ids.
 *
 *   Permissions-Policy
 *     Turns off device APIs this application never uses. Nothing here needs a
 *     camera, a microphone or a location.
 *
 *   Content-Security-Policy
 *     The one with teeth, and the one that breaks a page when it is slightly
 *     wrong. Report-only by default so an administrator can see what it WOULD
 *     have blocked before it blocks anything.
 *
 *   Strict-Transport-Security
 *     OFF BY DEFAULT AND IT MUST STAY OFF until separately approved. It is the
 *     only header here that cannot be taken back: a browser that has seen it
 *     refuses plain HTTP to this host for the whole max-age whatever the server
 *     later sends. A wrong value is not a setting somebody can switch off, it
 *     is an outage nobody can shorten. Gate 3 rule 8.
 *
 * The whole set is switchable, because a header that breaks an embedded view
 * should be turned off deliberately from a screen rather than by editing code
 * and deploying.
 */
class SecurityHeaders
{
    public function __construct(
        private readonly SecurityPolicies $policies,
    ) {}

    /**
     * The headers that do not depend on policy values.
     *
     * A constant rather than inline strings so `ApiSecurityAudit` can check
     * exactly what this middleware claims to send, instead of a hand-copied
     * list that drifts from it.
     *
     * @var array<string, string>
     */
    public const BASE_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    /**
     * The content security policy.
     *
     * `'self'` for everything, with two documented exceptions:
     *
     *  - `'unsafe-inline'` for styles, because the shell uses inline style
     *    attributes for a handful of computed widths. Removing them is worth
     *    doing and is not this gate's work; claiming a stricter policy than the
     *    application can meet would mean shipping it in report-only forever.
     *  - `data:` for images, because icons are inlined as data URIs.
     *
     * `frame-ancestors 'none'` repeats X-Frame-Options for browsers that
     * honour the CSP form and ignore the older header.
     */
    public const CONTENT_SECURITY_POLICY = "default-src 'self'; "
        ."script-src 'self'; "
        ."style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data:; "
        ."font-src 'self'; "
        ."connect-src 'self'; "
        ."form-action 'self'; "
        ."frame-ancestors 'none'; "
        ."base-uri 'self'; "
        .'object-src \'none\'';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->policies->enabled('api.security_headers')) {
            return $response;
        }

        foreach (self::BASE_HEADERS as $header => $value) {
            $response->headers->set($header, $value);
        }

        $mode = $this->policies->text('api.content_policy_mode');

        if ($mode === 'enforce') {
            $response->headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        } elseif ($mode === 'report_only') {
            $response->headers->set('Content-Security-Policy-Report-Only', self::CONTENT_SECURITY_POLICY);
        }

        /*
         * Only over HTTPS, and only when switched on. Sending HSTS over plain
         * HTTP is ignored by browsers anyway, but sending it from a local
         * development server would poison a developer's browser for every other
         * project on localhost.
         */
        if ($this->policies->enabled('api.hsts_enabled') && $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.($this->policies->number('api.hsts_max_age_days') * 86400),
            );
        }

        return $response;
    }
}
