<?php

declare(strict_types=1);

namespace App\Modules\Security\Catalogue;

/**
 * HOW ONE EVENT'S CONTEXT IS READ AS EVIDENCE. Declared per event, NEVER
 * inferred from a convention.
 *
 * THE CORRECTION THIS CLASS EXISTS FOR. The first P1-08 design asserted that
 * `user_id` is always the SUBJECT and `related_id` always the ACTOR. The
 * repository disproves it:
 *
 *   auth.login.succeeded      user_id is the person signing in   - the ACTOR
 *   user.deactivated          user_id is the administrator       - the ACTOR
 *   organisation.created      user_id is the creator             - the ACTOR
 *   group.member.added        user_id is the administrator, related_id the member
 *   access.role.assigned      user_id is the SUBJECT, related_id the ACTOR
 *   access.review.item.revoked   the same, inverted from user lifecycle
 *   auth.login.refused.unknown_identity   no user at all - a directory subject
 *   access.engine.failed      no actor at all - the system
 *
 * One rule applied across those would misattribute roughly half the estate,
 * plausibly and invisibly - an audit trail that reads correctly and names the
 * wrong person. So there is no rule: every event says what it means, and
 * EventSemanticsCompletenessTest asserts the declared set and the semantics map
 * are the SAME SET, as an equality. An event added without deciding its audit
 * meaning fails the build.
 */
final class EventSemantics
{
    public function __construct(
        public readonly AuditCategory $category,
        public readonly ActorSource $actor,
        public readonly SubjectSource $subject,
        public readonly TargetSource $target,
        public readonly OrganisationSource $organisation,
        public readonly OutcomeClass $outcome,
        /**
         * The target's type where the event does not emit `entity_type`.
         *
         * P1-05 and P1-07 carry `entity_id` without a type - the row is a role
         * assignment, an entitlement, a review item. Declaring the type here
         * keeps it out of a lookup: a type that had to be guessed from the id
         * would be guessed wrong the first time two tables shared a number.
         */
        public readonly ?string $targetType = null,
    ) {}
}
