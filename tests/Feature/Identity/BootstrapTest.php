<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Platform\Bootstrap\GrantIssuer;
use App\Modules\Platform\Http\Controllers\FirstRun\BeginController;
use App\Modules\Platform\Identity\Microsoft\EntraProvider;
use App\Modules\Platform\Models\BootstrapGrant;
use App\Modules\Platform\Models\PlatformRole;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EntraTokenFactory;
use Tests\TestCase;

/**
 * First-run bootstrap: negative cases 9 and 10, plus replay and concurrency.
 */
final class BootstrapTest extends TestCase
{
    use RefreshDatabase;

    private EntraTokenFactory $entra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entra = new EntraTokenFactory;
        $this->entra->configure();
    }

    public function test_the_first_administrator_is_created_through_entra(): void
    {
        $grant = $this->issueGrant();

        $this->get("/first-run/{$grant}")->assertOk();

        $this->completeSignIn($grant)->assertRedirect(route('console.home'));

        $admin = User::query()->sole();

        $this->assertSame(PlatformRole::SystemAdministrator, $admin->platform_role);
        $this->assertSame(UserStatus::Active, $admin->status);
        $this->assertSame('33333333-3333-3333-3333-333333333333', $admin->external_subject);
        $this->assertNotNull(BootstrapGrant::query()->sole()->consumed_at);
    }

    /** The grant on its own grants nothing (D-03 rule 3). */
    public function test_opening_the_grant_creates_no_administrator(): void
    {
        $grant = $this->issueGrant();

        $this->get("/first-run/{$grant}")->assertOk();

        $this->assertSame(0, User::query()->count());
        $this->assertNull(BootstrapGrant::query()->sole()->consumed_at);
    }

    /** Negative case 9. */
    public function test_bootstrap_is_closed_once_an_administrator_exists(): void
    {
        $grant = $this->issueGrant();
        $this->completeSignIn($grant);

        $this->assertSame(1, User::query()->count());

        $this->get("/first-run/{$grant}")->assertRedirect(route('first_run.closed'));

        $this->assertSame(1, User::query()->count(), 'A second administrator was created.');
    }

    /** A consumed grant cannot be replayed even while the system is unconfigured. */
    public function test_a_consumed_grant_cannot_be_reused(): void
    {
        $grant = $this->issueGrant();
        $this->completeSignIn($grant);

        User::query()->update(['status' => UserStatus::Inactive]);

        $this->get("/first-run/{$grant}")->assertRedirect(route('first_run.closed'));
        $this->assertSame(1, User::query()->count());
    }

    /**
     * Negative case 10. The grant must survive a wrong identity - refusing and
     * consuming would let anyone burn the setup link by clicking it.
     */
    public function test_a_wrong_identity_is_refused_without_consuming_the_grant(): void
    {
        $grant = $this->issueGrant('nominated@example.test');

        $this->completeSignIn($grant, ['email' => 'someone.else@example.test'])
            ->assertRedirect(route('auth.sign-in-unavailable'));

        $this->assertSame(0, User::query()->count(), 'An administrator was created for the wrong identity.');
        $this->assertNull(
            BootstrapGrant::query()->sole()->consumed_at,
            'The grant was consumed by a wrong identity, so the nominated administrator can no longer use it.'
        );
    }

    public function test_a_wrong_tenant_is_refused_without_consuming_the_grant(): void
    {
        $grant = $this->issueGrant();

        $this->completeSignIn($grant, ['tid' => '99999999-9999-9999-9999-999999999999'])
            ->assertRedirect(route('auth.access-denied'));

        $this->assertSame(0, User::query()->count());
        $this->assertNull(BootstrapGrant::query()->sole()->consumed_at);
    }

    public function test_an_expired_grant_is_refused(): void
    {
        $grant = $this->issueGrant();

        BootstrapGrant::query()->update(['expires_at' => now()->subMinute()]);

        $this->get("/first-run/{$grant}")->assertRedirect(route('first_run.closed'));
        $this->assertSame(0, User::query()->count());
    }

    public function test_an_unknown_grant_is_refused(): void
    {
        $this->get('/first-run/'.str_repeat('a', 64))->assertRedirect(route('first_run.closed'));
    }

    /** Only the hash is stored; the plaintext must never reach the database. */
    public function test_the_grant_is_stored_only_as_a_hash(): void
    {
        $grant = $this->issueGrant();

        $stored = BootstrapGrant::query()->sole();

        $this->assertNotSame($grant, $stored->token_hash);
        $this->assertSame(hash('sha256', $grant), $stored->token_hash);
        $this->assertStringNotContainsString($grant, json_encode($stored->toArray()));
    }

    /** A grant already consumed before the request even starts. */
    public function test_a_grant_consumed_before_the_request_is_refused(): void
    {
        $grant = $this->issueGrant();

        BootstrapGrant::query()->update(['consumed_at' => now()]);

        $this->completeSignIn($grant)->assertRedirect(route('auth.sign-in-unavailable'));

        $this->assertSame(0, User::query()->count());
    }

    /**
     * The real race, and the reason the single-use guard lives in the WHERE
     * clause rather than in PHP.
     *
     * The first version of this test consumed the grant before the request
     * began, which only ever exercised the initial lookup - the UPDATE was
     * never reached, and removing the guard from the WHERE clause did not make
     * it fail. It was a test of the wrong thing that looked like a test of the
     * right one.
     *
     * This consumes the grant AFTER the lookup has succeeded and the user row
     * has been written, which is precisely the window a second concurrent
     * redemption occupies. Exactly one row must be affected; zero means we
     * lost the race, and the whole transaction - including the administrator -
     * must roll back.
     */
    public function test_a_grant_consumed_mid_transaction_creates_no_administrator(): void
    {
        $grant = $this->issueGrant();

        User::created(function (): void {
            // The competing request wins, between our lookup and our update.
            BootstrapGrant::query()->whereNull('consumed_at')->update(['consumed_at' => now()]);
        });

        $this->completeSignIn($grant)->assertRedirect(route('auth.sign-in-unavailable'));

        $this->assertSame(
            0,
            User::query()->count(),
            'An administrator survived a lost race for the grant. The transaction did not roll back.'
        );
    }

    public function test_the_issuer_refuses_while_an_administrator_exists(): void
    {
        $grant = $this->issueGrant();
        $this->completeSignIn($grant);

        $this->assertSame(1, User::query()->count());

        $this->expectExceptionMessage('Bootstrap is closed');

        app(GrantIssuer::class)->issue('another@example.test', EntraTokenFactory::TENANT);
    }

    /**
     * Recovery: the same operator channel reopens only when no active System
     * Administrator remains. It is not a flag - it is the state predicate
     * returning true again.
     */
    public function test_recovery_is_possible_only_with_no_active_administrator(): void
    {
        $grant = $this->issueGrant();
        $this->completeSignIn($grant);

        User::query()->update(['status' => UserStatus::Inactive]);

        $recovery = app(GrantIssuer::class)->issue('replacement@example.test', EntraTokenFactory::TENANT);

        $this->assertNotSame('', $recovery);
        $this->assertSame(2, BootstrapGrant::query()->count());
    }

    private function issueGrant(string $subject = 'person@example.test'): string
    {
        return app(GrantIssuer::class)->issue($subject, EntraTokenFactory::TENANT);
    }

    private function completeSignIn(string $grant, array $claimOverrides = [])
    {
        $this->entra->fakeEndpoints($this->entra->token($claimOverrides));

        return $this->withSession([
            BeginController::SESSION_GRANT => $grant,
            EntraProvider::SESSION_STATE => 'the-state',
            EntraProvider::SESSION_NONCE => 'test-nonce',
            EntraProvider::SESSION_VERIFIER => 'verifier',
        ])->get('/auth/microsoft/callback?code=abc&state=the-state');
    }
}
