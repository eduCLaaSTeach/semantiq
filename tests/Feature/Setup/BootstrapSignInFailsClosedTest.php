<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Audit\Models\AuditChainHead;
use App\Modules\Platform\Security\EvidenceNotRecorded;
use App\Modules\Platform\Setup\Bootstrap\BootstrapAuthenticator;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * B15. CORRECTION 6: A BOOTSTRAP SIGN-IN THAT CANNOT BE EVIDENCED ISSUES NO
 * SESSION.
 *
 * The first draft classed bootstrap.signin.succeeded as BestEffort, which in
 * this codebase means exactly one thing: if the write fails, carry on. Carrying
 * on here means issuing a session for the most privileged local credential in
 * the deployment with no record that it was ever used - so anybody able to make
 * audit writes fail gets a silent sign-in, and afterwards nobody can answer
 * "was this used?".
 *
 * THE CREDENTIAL SUPPLIED HERE IS THE CORRECT ONE, and that is the whole design
 * of the case. A test that also got the password wrong would be satisfied by
 * ANY refusal - including the ordinary one - and would pass just as happily
 * with BestEffort restored. It would be a test about nothing, which is the
 * failure CLAUDE.md section 2 names.
 *
 * THE ASSERTION IS THE SESSION, NOT THE EXCEPTION. Session state is not
 * transactional: a rollback cannot take a session key back. Only "no session
 * exists" catches the ordering defect, which is why P1-08's own sign-in case is
 * written the same way.
 */
final class BootstrapSignInFailsClosedTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'the-operator-typed-this-once';

    private function request(): Request
    {
        $request = Request::create('/first-run/sign-in', 'POST');
        $request->setLaravelSession(new Store('test', new ArraySessionHandler(120)));

        return $request;
    }

    private function givenALocalAdministrator(): void
    {
        BootstrapAdministrator::query()->create([
            'singleton' => BootstrapAdministrator::SINGLETON,
            'email' => 'setup@example.test',
            'password_hash' => Hash::make(self::PASSWORD),
        ]);
    }

    /**
     * Make the real write path fail, WITHOUT DDL.
     *
     * Every append reads the chain head under a lock and cannot proceed
     * without it, so removing that row is a genuine storage failure reaching
     * AuditWriter exactly as a full disk would. Dropping a table would commit
     * the surrounding transaction out from under the test on MySQL.
     */
    private function breakTheEvidenceStore(): void
    {
        AuditChainHead::query()->delete();
    }

    /** The correct credential works while evidence CAN be written. Otherwise B15 proves nothing. */
    public function test_the_correct_credential_is_accepted_while_evidence_can_be_recorded(): void
    {
        $this->givenALocalAdministrator();

        $request = $this->request();

        $this->assertNotNull(
            app(BootstrapAuthenticator::class)->attempt($request, 'setup@example.test', self::PASSWORD),
            'The correct credential was refused before the evidence store was broken, so the case '
            .'below would pass for the wrong reason.',
        );

        $this->assertSame(
            BootstrapAdministrator::current()?->id,
            $request->session()->get(BootstrapAuthenticator::SESSION_KEY),
        );
    }

    /**
     * B15. Evidence fails, the credential is CORRECT, and no session is issued.
     *
     * Mutation: declare bootstrap.signin.succeeded as BestEffort in
     * EventCatalogue, as the first draft did. The write then fails silently,
     * attempt() returns a principal and this case fails.
     */
    public function test_b15_a_bootstrap_sign_in_that_cannot_be_evidenced_issues_no_session(): void
    {
        $this->givenALocalAdministrator();
        $this->breakTheEvidenceStore();

        $request = $this->request();

        try {
            app(BootstrapAuthenticator::class)->attempt($request, 'setup@example.test', self::PASSWORD);
            $this->fail('A bootstrap sign-in completed although its evidence could not be written.');
        } catch (EvidenceNotRecorded) {
            // The correct outcome: the attempt does not complete.
        }

        $this->assertNull(
            $request->session()->get(BootstrapAuthenticator::SESSION_KEY),
            'A BOOTSTRAP SESSION EXISTS FOR A SIGN-IN NOBODY CAN PROVE HAPPENED. This is the most '
            .'privileged local credential in the deployment.',
        );
    }

    /** ...and nothing about the principal was updated either. */
    public function test_b15_an_unevidenced_attempt_leaves_no_trace_of_success(): void
    {
        $this->givenALocalAdministrator();
        $this->breakTheEvidenceStore();

        try {
            app(BootstrapAuthenticator::class)->attempt($this->request(), 'setup@example.test', self::PASSWORD);
        } catch (EvidenceNotRecorded) {
            // Expected.
        }

        $this->assertNull(
            BootstrapAdministrator::current()?->last_signed_in_at,
            'last_signed_in_at records a sign-in that was refused, so the row disagrees with the '
            .'audit trail about whether somebody got in.',
        );
    }

    /**
     * A REFUSAL KEEPS REFUSAL SEMANTICS.
     *
     * Hardening bootstrap.signin.refused into a failure would let a broken
     * audit store lock somebody out of recovery while granting nothing - the
     * wrong direction to fail in. So a WRONG password with a broken evidence
     * store must still simply refuse, not raise.
     */
    public function test_a_refusal_still_refuses_when_evidence_cannot_be_written(): void
    {
        $this->givenALocalAdministrator();
        $this->breakTheEvidenceStore();

        $request = $this->request();

        $this->assertNull(
            app(BootstrapAuthenticator::class)->attempt($request, 'setup@example.test', 'the-wrong-password'),
            'A refusal raised instead of refusing when its evidence could not be written.',
        );

        $this->assertNull($request->session()->get(BootstrapAuthenticator::SESSION_KEY));
    }
}
