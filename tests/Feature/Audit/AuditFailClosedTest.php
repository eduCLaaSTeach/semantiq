<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Audit\Models\AuditChainHead;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Identity\Microsoft\EntraProvider;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Security\EvidenceNotRecorded;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EntraTokenFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * A11 - A13, A20. D-111, FAIL CLOSED - AND THE PLACES IT MUST NOT APPLY.
 *
 * THE FAILURE IS INDUCED THROUGH THE REAL WRITE PATH. The chain head is
 * removed, so AuditWriter::append() genuinely cannot complete. A stub recorder
 * that threw would prove the catch block runs and nothing about whether the
 * real path is wired to it - the EngineBoundaryTest precedent, for the same
 * reason.
 *
 * IT USED TO DROP THE TABLE, AND THAT PASSED LOCALLY AND FAILED ON MySQL.
 * MySQL commits the open transaction implicitly on DDL, so Schema::drop()
 * silently ended RefreshDatabase's wrapping transaction while Laravel went on
 * believing it was still inside one - and the next savepoint rollback failed
 * with "SAVEPOINT trans2 does not exist". Four cases were green on SQLite and
 * red on the engine production uses. Deleting a row is ordinary DML and
 * behaves the same on both.
 */
final class AuditFailClosedTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private EntraTokenFactory $entra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->entra = new EntraTokenFactory;
        $this->entra->configure();
    }

    /**
     * A11. A SUCCESSFUL SIGN-IN THAT CANNOT BE EVIDENCED ISSUES NO SESSION.
     *
     * THE ASSERTION IS THE SESSION, NOT THE EXCEPTION. Session state is not
     * transactional, so the old order - regenerate, write the keys, then record
     * - left somebody signed in and unevidenced when the write failed, and
     * nothing downstream could tell. Only "no session exists" catches that.
     *
     * Mutation: move the record() call back below the session writes in
     * CallbackController::issueSession(). The session is then populated and
     * this fails.
     */
    public function test_a_sign_in_that_cannot_be_evidenced_issues_no_session(): void
    {
        User::query()->create([
            'provider' => 'microsoft',
            'external_subject' => '33333333-3333-3333-3333-333333333333',
            'tenant_id' => EntraTokenFactory::TENANT,
            'email' => 'person@example.test',
            'display_name' => 'Test Person',
            'status' => UserStatus::Active,
        ]);

        $this->breakTheEvidenceStore();

        $this->entra->fakeEndpoints($this->entra->token());

        $response = $this->withSession([
            EntraProvider::SESSION_STATE => 'the-state',
            EntraProvider::SESSION_NONCE => 'test-nonce',
            EntraProvider::SESSION_VERIFIER => 'verifier',
        ])->get('/auth/microsoft/callback?code=abc&state=the-state');

        // The ordinary refusal screen, not an exception. An unevidenced
        // sign-in is a security failure; a stack trace on a login page is one
        // too, and the person cannot act on either.
        $response->assertRedirect(route('auth.sign-in-unavailable'));
        $response->assertSessionMissing(EnsureSessionIsCurrent::SESSION_USER_ID);
        $this->assertNull(
            session(EnsureSessionIsCurrent::SESSION_USER_ID),
            'Somebody was signed in without their sign-in being recorded.'
        );
    }

    /**
     * A13. A STATE CHANGE THAT CANNOT BE EVIDENCED DOES NOT HAPPEN.
     *
     * Mutation: catch the EvidenceNotRecorded in AuditWriter and continue. The role
     * is then granted with no evidence that it was.
     */
    public function test_a_privileged_change_that_cannot_be_evidenced_is_rolled_back(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);

        $this->breakTheEvidenceStore();

        try {
            app(RoleAssignmentService::class)->assign($subject, RoleCode::BusinessUser, $organisation->id, $admin);
            $this->fail('A role was granted although it could not be recorded.');
        } catch (EvidenceNotRecorded $violation) {
            $this->assertSame('evidence_not_recorded', $violation->reason);
        }

        $this->assertSame(
            0,
            RoleAssignment::query()
                ->where('user_id', $subject->id)
                ->where('role_code', RoleCode::BusinessUser->value)
                ->count(),
            'The change survived although its evidence did not.'
        );
    }

    /**
     * A12 / A20. A REFUSAL WHOSE OWN EVIDENCE FAILS IS STILL A REFUSAL.
     *
     * Failing closed may turn a success into a failure. It must NEVER turn a
     * refusal into anything else, and it must never raise an exception out of a
     * path whose whole job was to refuse.
     *
     * Mutation: use writeOrFail() for every outcome class. This then throws.
     */
    public function test_a_refusal_whose_evidence_fails_stays_refused(): void
    {
        $this->breakTheEvidenceStore();

        app(SecurityEventLogger::class)->record(SecurityEventLogger::LOGIN_REFUSED_UNKNOWN, [
            'provider' => 'microsoft',
            'subject' => 'outsider-guid',
            'tenant' => 'tenant-a',
            'result' => 'refused',
            'reason' => 'unknown_identity',
        ]);

        // Reached here at all: no exception escaped the refusal path.
        $this->assertTrue(true);
    }

    /**
     * Sign-out is BEST EFFORT and is never prevented. Ruled, not assumed.
     *
     * Mutation: class auth.logout as a StateChange. Somebody then cannot sign
     * out while the evidence store is unavailable, which is worse than the gap.
     */
    public function test_sign_out_is_never_prevented(): void
    {
        $user = $this->make->user($this->make->organisation());

        $this->breakTheEvidenceStore();

        $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ])->post('/auth/logout')->assertRedirect(route('auth.signed-out'));
    }

    /**
     * Make the real write path fail, WITHOUT DDL.
     *
     * Every append reads the chain head under a lock and cannot proceed
     * without it, so removing that row is a genuine storage failure reaching
     * AuditWriter exactly as a full disk would - and, unlike dropping a table,
     * it does not commit the surrounding transaction out from under the test.
     */
    private function breakTheEvidenceStore(): void
    {
        AuditChainHead::query()->delete();
    }
}
