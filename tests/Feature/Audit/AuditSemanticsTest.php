<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Security\Catalogue\ActorSource;
use App\Modules\Security\Catalogue\EventCatalogue;
use App\Modules\Security\Catalogue\OutcomeClass;
use App\Modules\Security\Catalogue\SubjectSource;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * A21. EVERY DECLARED EVENT HAS DECLARED AUDIT SEMANTICS.
 *
 * Asserted as an EQUALITY in both directions, which is the only form that
 * catches both halves: an event added without semantics, and a semantics entry
 * for an event nobody declares.
 */
final class AuditSemanticsTest extends TestCase
{
    /** Mutation: add an event to SecurityEventLogger and not to SEMANTICS. */
    public function test_every_declared_event_has_declared_audit_semantics(): void
    {
        $declared = SecurityEventLogger::events();
        $described = EventCatalogue::semanticsKeys();

        sort($declared);
        sort($described);

        $this->assertSame(
            $declared,
            $described,
            'An event exists whose audit meaning nobody decided. A default bucket is how '
            .'an unclassified event quietly becomes somebody else\'s actor.'
        );
    }

    /** Mutation: add a `default =>` fallback to semanticsFor(). */
    public function test_an_undeclared_event_throws_rather_than_defaulting(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EventCatalogue::semanticsFor('some.event.nobody.declared');
    }

    /**
     * THE CASE THIS WHOLE MECHANISM EXISTS FOR.
     *
     * User lifecycle and role administration put OPPOSITE things in `user_id`.
     * If a future edit ever made them agree, one of them would be wrong — and
     * the audit trail would read perfectly while naming the wrong person.
     *
     * Mutation: set both to the same actor source. This fails.
     */
    public function test_user_lifecycle_and_role_administration_are_inverses(): void
    {
        $lifecycle = EventCatalogue::semanticsFor('user.deactivated');
        $roles = EventCatalogue::semanticsFor('access.role.assigned');

        $this->assertSame(ActorSource::UserId, $lifecycle->actor);
        $this->assertSame(SubjectSource::EntityId, $lifecycle->subject);

        $this->assertSame(ActorSource::RelatedId, $roles->actor);
        $this->assertSame(SubjectSource::UserId, $roles->subject);

        $this->assertNotSame(
            $lifecycle->actor,
            $roles->actor,
            'These two events disagree about where the actor is, and any single global '
            .'convention names the wrong person in one of them.'
        );
    }

    /** A refused sign-in has no SemantIQ actor, and none is invented. */
    public function test_a_refused_sign_in_declares_an_external_actor(): void
    {
        $this->assertSame(
            ActorSource::ExternalSubject,
            EventCatalogue::semanticsFor('auth.login.refused.unknown_identity')->actor,
        );

        $this->assertSame(
            ActorSource::System,
            EventCatalogue::semanticsFor('access.engine.failed')->actor,
        );
    }

    /**
     * Refusals are declared as refusals, so they are written outside the
     * transaction that is about to roll back.
     *
     * Mutation: class `access.review.refused` as a StateChange. It then vanishes
     * with the rollback it was recording.
     */
    public function test_refusals_are_declared_as_refusals(): void
    {
        foreach ([
            'access.review.refused',
            'user.provision.refused',
            'access.step_up.refused',
            'auth.login.refused.inactive',
            'bootstrap.refused',
        ] as $event) {
            $this->assertSame(
                OutcomeClass::Refusal,
                EventCatalogue::semanticsFor($event)->outcome,
                "{$event} must not join the transaction it is refusing."
            );
        }

        foreach (['auth.logout', 'auth.session.expired'] as $event) {
            $this->assertSame(OutcomeClass::BestEffort, EventCatalogue::semanticsFor($event)->outcome);
        }

        /*
         * A SUCCESSFUL SIGN-IN FAILS CLOSED BY ORDER, NOT BY TRANSACTION.
         *
         * The state it changes is the session, which is not in the database, so
         * there is no transaction that could roll it back. The evidence is
         * written first instead, and no session is issued if it fails - which
         * AuditFailClosedTest asserts on the SESSION rather than on the throw.
         */
        $this->assertSame(
            OutcomeClass::StateChangeRecordedFirst,
            EventCatalogue::semanticsFor('auth.login.succeeded')->outcome,
        );

        // And the health note is best effort, because it is a CACHE entry about
        // whether Microsoft is reachable - nothing to roll back, and no
        // transaction that could.
        $this->assertSame(
            OutcomeClass::BestEffort,
            EventCatalogue::semanticsFor('identity.health.state_changed')->outcome,
        );

        // Everything that genuinely changes the database must fail closed.
        foreach (['access.role.assigned', 'user.deactivated', 'organisation.updated', 'group.member.removed'] as $event) {
            $this->assertSame(
                OutcomeClass::StateChange,
                EventCatalogue::semanticsFor($event)->outcome,
                "{$event} changes the database and must fail closed."
            );
        }
    }
}
