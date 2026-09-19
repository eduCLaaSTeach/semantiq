<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\People\Services\UserDirectoryService;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AccessFactory;
use Tests\Support\EmitsEvidence;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * A1 - A5, A16. WHAT IS ACTUALLY STORED, AND WHO IT SAYS DID IT.
 *
 * These assert the ROW, not the log line. A test that asserted the log would
 * pass on a deployment that stores nothing, which is the state P1-08 exists to
 * end.
 */
final class AuditEvidenceTest extends TestCase
{
    use EmitsEvidence;
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    /**
     * A1. A successful sign-in is evidenced, with the right actor.
     *
     * Mutation: drop the persist from SecurityEventLogger::record(). Nothing is
     * stored and this fails on the first assertion.
     */
    public function test_a_successful_sign_in_is_evidenced(): void
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation);

        $this->emit(SecurityEventLogger::LOGIN_SUCCEEDED, [
            'provider' => 'microsoft',
            'subject' => $user->external_subject,
            'tenant' => $user->tenant_id,
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'result' => 'succeeded',
        ]);

        $row = AuditEvent::query()->sole();

        $this->assertSame('auth.login.succeeded', $row->event);
        $this->assertSame('user_access', $row->category->value);
        $this->assertSame('person', $row->actor_type);
        // THE ACTOR IS THE PERSON SIGNING IN. user_id is not the subject here.
        $this->assertSame($user->id, (int) $row->actor_user_id);
        $this->assertSame($user->id, (int) $row->subject_user_id);
        $this->assertSame($organisation->id, (int) $row->organisation_id);
        $this->assertNotNull($row->occurred_at);
    }

    /**
     * A2. A refused sign-in is evidenced WITHOUT a fabricated actor, for every
     * refusal reason.
     *
     * Mutation: resolve actor_user_id by looking the subject up. The first two
     * cases then name somebody.
     */
    public function test_refused_sign_ins_are_evidenced_without_inventing_an_actor(): void
    {

        $this->emit(SecurityEventLogger::LOGIN_REFUSED_UNKNOWN, [
            'provider' => 'microsoft', 'subject' => 'outsider-guid', 'tenant' => 'tenant-a',
            'result' => 'refused', 'reason' => 'unknown_identity',
        ]);
        $this->emit(SecurityEventLogger::LOGIN_REFUSED_TENANT, [
            'provider' => 'microsoft', 'subject' => 'other-guid', 'tenant' => 'tenant-b',
            'result' => 'refused', 'reason' => 'tenant',
        ]);
        $this->emit(SecurityEventLogger::LOGIN_REFUSED_PROTOCOL, [
            'provider' => 'microsoft', 'subject' => 'third-guid', 'tenant' => 'tenant-c',
            'result' => 'refused', 'reason' => 'protocol',
        ]);

        $inactive = $this->make->user($this->make->organisation());
        $this->emit(SecurityEventLogger::LOGIN_REFUSED_INACTIVE, [
            'provider' => 'microsoft', 'user_id' => $inactive->id,
            'organisation_id' => $inactive->organisation_id,
            'result' => 'refused', 'reason' => 'inactive',
        ]);

        $rows = AuditEvent::query()->orderBy('sequence')->get();

        $this->assertCount(4, $rows);

        foreach ($rows->take(3) as $row) {
            $this->assertSame('external_subject', $row->actor_type);
            $this->assertNull($row->actor_user_id, 'A refused sign-in was given a SemantIQ actor.');
            $this->assertNotNull($row->actor_subject);
            $this->assertSame('security_events', $row->category->value);
            $this->assertNull($row->organisation_id, 'An unknown identity belongs to no organisation.');
        }

        // The inactive case DOES have a person - they exist, they are just not
        // permitted to sign in.
        $this->assertSame('person', $rows[3]->actor_type);
        $this->assertSame($inactive->id, (int) $rows[3]->actor_user_id);
    }

    /**
     * A3. A privileged change names actor, subject and target.
     *
     * Mutation: read the actor from user_id for access events. It then records
     * the SUBJECT as the person who made the change.
     */
    public function test_a_privileged_change_names_actor_subject_and_target(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);

        AuditEvent::query()->delete();

        $assignment = $this->access->assignment($subject, RoleCode::BusinessUser, $organisation);

        $this->emit(SecurityEventLogger::ROLE_ASSIGNED, [
            'user_id' => $subject->id,
            'related_id' => $admin->id,
            'organisation_id' => $organisation->id,
            'role' => RoleCode::BusinessUser->value,
            'entity_id' => $assignment->id,
            'result' => 'assigned',
        ]);

        $row = AuditEvent::query()->where('event', SecurityEventLogger::ROLE_ASSIGNED)->sole();

        $this->assertSame($admin->id, (int) $row->actor_user_id, 'The subject was recorded as the actor.');
        $this->assertSame($subject->id, (int) $row->subject_user_id);
        $this->assertSame('role_assignment', $row->target_type);
        $this->assertSame($assignment->id, (int) $row->target_id);
        $this->assertSame('assigned', $row->outcome);
    }

    /**
     * A5. THE CASE THE FIRST DESIGN GOT WRONG. Actor correctness in ALL FIVE
     * areas, because any single global convention satisfies one and fails
     * another.
     *
     * Mutation: apply ANY one rule - `user_id is the actor` or
     * `related_id is the actor`. Each breaks at least two rows below.
     */
    public function test_the_actor_is_correct_in_every_area(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        AuditEvent::query()->delete();

        // 1. LOGIN - the actor is in user_id.
        $this->emit(SecurityEventLogger::LOGIN_SUCCEEDED, [
            'provider' => 'microsoft', 'subject' => $person->external_subject,
            'tenant' => $person->tenant_id, 'user_id' => $person->id,
            'organisation_id' => $organisation->id, 'result' => 'succeeded',
        ]);

        // 2. USER LIFECYCLE - the actor is the ADMINISTRATOR in user_id.
        $this->emit(SecurityEventLogger::USER_DEACTIVATED, [
            'user_id' => $admin->id, 'organisation_id' => $organisation->id,
            'entity_type' => 'user', 'entity_id' => $person->id, 'result' => 'deactivated',
        ]);

        // 3. ORGANISATION ADMINISTRATION - the actor is the creator.
        $this->emit(SecurityEventLogger::TEAM_MOVED, [
            'user_id' => $admin->id, 'organisation_id' => $organisation->id,
            'entity_type' => 'teams', 'entity_id' => 7, 'result' => 'moved',
        ]);

        // 4. ROLE ADMINISTRATION - the actor is in RELATED_ID. The inverse.
        $this->emit(SecurityEventLogger::ROLE_REVOKED, [
            'user_id' => $person->id, 'related_id' => $admin->id,
            'organisation_id' => $organisation->id, 'role' => RoleCode::BusinessUser->value,
            'entity_id' => 11, 'result' => 'revoked',
        ]);

        // 5. ACCESS REVIEWS - the reviewer is in related_id.
        $this->emit(SecurityEventLogger::REVIEW_ITEM_REVOKED, [
            'user_id' => $person->id, 'related_id' => $admin->id,
            'organisation_id' => $organisation->id, 'entity_id' => 4, 'result' => 'revoke',
        ]);

        $by = AuditEvent::query()->get()->keyBy('event');

        $this->assertSame($person->id, (int) $by['auth.login.succeeded']->actor_user_id);
        $this->assertSame($admin->id, (int) $by['user.deactivated']->actor_user_id);
        $this->assertSame($person->id, (int) $by['user.deactivated']->subject_user_id);
        $this->assertSame($admin->id, (int) $by['team.moved']->actor_user_id);
        $this->assertSame($admin->id, (int) $by['access.role.revoked']->actor_user_id);
        $this->assertSame($person->id, (int) $by['access.role.revoked']->subject_user_id);
        $this->assertSame($admin->id, (int) $by['access.review.item.revoked']->actor_user_id);
        $this->assertSame($person->id, (int) $by['access.review.item.revoked']->subject_user_id);
    }

    /**
     * A16. D-101. There is no column for network detail, so it cannot be stored
     * even by mistake.
     *
     * Mutation: add an ip_address column.
     */
    public function test_no_network_detail_is_stored(): void
    {
        $columns = Schema::getColumnListing('audit_events');

        $this->assertNotContains('ip_address', $columns);
        $this->assertNotContains('user_agent', $columns);

        // And no free-text column either. Every string column is a fixed
        // vocabulary, an identifier or a name-free reference.
        $this->assertNotContains('note', $columns);
        $this->assertNotContains('message', $columns);
        $this->assertNotContains('detail', $columns);
    }

    /**
     * A22. context_expires_at - the permitted key the first design forgot - is
     * stored AND hashed.
     *
     * Mutation: drop it from AuditHash::FIELDS. The coverage test fails; drop
     * the column and this one does.
     */
    public function test_the_grant_expiry_is_stored(): void
    {
        $expires = now()->addMinutes(30);

        $this->emit(SecurityEventLogger::BOOTSTRAP_GRANT_ISSUED, [
            'subject' => 'founder@example.com',
            'tenant' => 'tenant-a',
            'expires_at' => $expires->toIso8601String(),
            'result' => 'issued',
        ]);

        $row = AuditEvent::query()->sole();

        $this->assertNotNull($row->context_expires_at, 'The grant TTL was not stored.');
        $this->assertStringStartsWith($expires->format('Y-m-d H:i'), (string) $row->context_expires_at);
    }

    /**
     * A user purge must not take their evidence with it, and must not be
     * blocked by it. That is why actor_user_id carries no foreign key.
     *
     * Mutation: add a foreign key with restrictOnDelete. The purge then fails.
     */
    public function test_evidence_survives_the_purge_of_the_person_it_names(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $this->emit(SecurityEventLogger::USER_DEACTIVATED, [
            'user_id' => $admin->id, 'organisation_id' => $organisation->id,
            'entity_type' => 'user', 'entity_id' => $person->id, 'result' => 'deactivated',
        ]);

        app(UserDirectoryService::class)->purge($person->fresh(), $admin);

        $this->assertNull(User::query()->find($person->id));
        $this->assertNotNull(
            AuditEvent::query()->where('event', SecurityEventLogger::USER_DEACTIVATED)->first(),
            'Purging a person destroyed the evidence about them.'
        );
    }
}
