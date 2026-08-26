<?php

declare(strict_types=1);

namespace App\Modules\Governance\Privacy;

use App\Modules\Governance\Models\PrivacyRequest;

/**
 * A source of personal data about one subject.
 *
 * ONE COLLECTOR PER TABLE, registered in `CollectorCatalogue`. Adding a table
 * to the scope of a subject access response is therefore a code review, for the
 * same reason the permission registry is code rather than rows: a disclosure
 * scope that can be widened by editing a database record can be widened without
 * anybody seeing it happen.
 *
 * `tables()` is what the structural coverage test reconciles against the live
 * schema. A table that no collector claims and no exclusion explains fails the
 * build.
 */
interface SubjectCollector
{
    /**
     * The tables this collector is responsible for.
     *
     * @return list<string>
     */
    public function tables(): array;

    /**
     * What this collector holds about the subject, already rendered.
     *
     * IMPLEMENTATIONS MUST NOT RETURN ANOTHER PERSON'S IDENTITY in a `describe`
     * summary. The convention that makes that easy to honour: never load the
     * other party. Query for the subject's own involvement, and render from the
     * date and the action alone.
     *
     * @return list<CollectedItem>
     */
    public function collect(PrivacyRequest $request): array;
}
