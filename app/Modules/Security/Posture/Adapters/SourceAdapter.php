<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture\Adapters;

use App\Modules\Security\Posture\Evidence;

/**
 * A read-only window onto one source unit.
 *
 * FOUR RULES, and each is enforced rather than trusted:
 *
 *   READ-ONLY            an adapter returns Evidence. It has no write path, and
 *                        N-SS24's architecture guard asserts no DB write, no
 *                        save(), no update(), no delete() and no Cache::put()
 *                        anywhere in this namespace.
 *
 *   NO DUPLICATED RULE   an adapter may COUNT and READ STATE. It may never
 *                        DECIDE. Every decision is delegated to the owning
 *                        unit's own service, so posture cannot become a second
 *                        opinion about access.
 *
 *   NO PROBE ON RENDER   N-SS37 asserts no call to EntraDiscovery::probe(),
 *                        IdentityHealthCheck::recheck() or any HTTP client from
 *                        this namespace. "Live" means recomputed from current
 *                        authoritative state, not "phone Microsoft on every
 *                        page view".
 *
 *   FAILURE IS UNVERIFIED
 *                        PostureEvaluator wraps every call in a catch and
 *                        yields Unverified WITH THE ROW STILL PRESENT. The
 *                        thrown value is DISCARDED, never formatted into the
 *                        finding: an exception message is where a connection
 *                        string, a table name or a path leaks onto a screen.
 *
 * @return list<Evidence>
 */
interface SourceAdapter
{
    /** @return list<Evidence> */
    public function evidence(): array;

    /**
     * The control identifiers this adapter answers for.
     *
     * Declared rather than inferred, so N-SS7 can assert that every catalogued
     * control has exactly one evaluator - a control with none fails the build
     * instead of rendering as a missing row.
     *
     * @return list<string>
     */
    public function answers(): array;
}
