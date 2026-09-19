<?php

declare(strict_types=1);

namespace App\Modules\Security\Catalogue;

/**
 * WHICH ORGANISATION'S EVIDENCE THIS IS. D-99.
 *
 * `None` is not "unknown" - it is PLATFORM-SCOPED, and a platform-scoped row is
 * System Administrator only. The projection reads it as an explicit
 * `organisation_id IS NULL` branch on that one viewer, never as an omitted
 * WHERE clause, because an omitted clause is how "no organisation" quietly
 * becomes "every organisation".
 */
enum OrganisationSource: string
{
    case Context = 'context';

    case None = 'none';
}
