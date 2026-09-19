<?php

declare(strict_types=1);

namespace App\Modules\Security\Catalogue;

/** WHO THE EVENT IS ABOUT, where that differs from who did it. */
enum SubjectSource: string
{
    case UserId = 'user_id';

    case RelatedId = 'related_id';

    /** The affected person IS the target row - P1-03's user lifecycle. */
    case EntityId = 'entity_id';

    case ExternalSubject = 'external_subject';

    /** The event is about a thing, not a person. */
    case None = 'none';
}
