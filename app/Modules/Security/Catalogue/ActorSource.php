<?php

declare(strict_types=1);

namespace App\Modules\Security\Catalogue;

/** WHERE THE ACTOR COMES FROM, per event. Never guessed. */
enum ActorSource: string
{
    /** The `user_id` context key IS the actor. */
    case UserId = 'user_id';

    /** The `related_id` context key is the actor - P1-05 and P1-07's shape. */
    case RelatedId = 'related_id';

    /**
     * A directory identity with no SemantIQ account. A refused sign-in has no
     * user, which is the point of refusing it, and NO LOOKUP IS ATTEMPTED to
     * produce one.
     */
    case ExternalSubject = 'external_subject';

    /** No human actor: an engine condition, an unattended check. */
    case System = 'system';
}
