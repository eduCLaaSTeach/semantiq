<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Identity\Microsoft\EntraProvider;
use App\Modules\Platform\Models\PlatformRole;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EntraTokenFactory;
use Tests\TestCase;

/**
 * The end-to-end path and its refusals, through the real routes.
 *
 * Negative cases 1, 2, 3, 6, 8, 11 and 12.
 */
final class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    private EntraTokenFactory $entra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entra = new EntraTokenFactory;
        $this->entra->configure();
    }

    /** Negative case 12. */
    public function test_an_unauthenticated_request_to_a_protected_url_reaches_the_login_page(): void
    {
        $response = $this->get('/console');

        $response->assertRedirect(route('entry'));

        $body = $this->get('/')->getContent();

        foreach (['Administration', 'Organisation', 'Business Domains', 'Fabric', 'Workplace'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, "The Login page leaked [{$leak}].");
        }
    }

    public function test_the_redirect_sends_the_browser_to_entra_with_pkce(): void
    {
        $this->entra->fakeEndpoints();

        $response = $this->get('/auth/microsoft/redirect');

        $location = $response->headers->get('Location');

        $this->assertStringContainsString('code_challenge_method=S256', $location);
        $this->assertStringContainsString('scope='.urlencode('openid profile email'), $location);
        $this->assertStringNotContainsString('Mail.', $location);
        $this->assertStringNotContainsString('Directory.', $location);
    }

    /** Negative case 1 and 2: the guard against self-registration. */
    public function test_an_unknown_identity_is_refused_and_creates_no_user(): void
    {
        $this->assertSame(0, User::query()->count());

        $response = $this->completeSignIn();

        $response->assertRedirect(route('auth.access-not-assigned'));

        $this->assertSame(0, User::query()->count(), 'Authentication created a user. Self-registration is disabled.');
        $this->assertNull(session(EnsureSessionIsCurrent::SESSION_USER_ID));
    }

    /**
     * Negative case 3.
     *
     * Lands on access-not-assigned, not account-inactive, and that is
     * deliberate: to an anonymous caller the two outcomes must be
     * indistinguishable, or the difference itself reveals which addresses are
     * real SemantIQ accounts. The security log still records "inactive".
     */
    public function test_an_inactive_user_is_refused(): void
    {
        $this->existingUser(UserStatus::Inactive);

        $this->completeSignIn()->assertRedirect(route('auth.access-not-assigned'));

        $this->assertNull(session(EnsureSessionIsCurrent::SESSION_USER_ID));
    }

    /**
     * The enumeration guard, at the level that matters: an attacker probing
     * with real and invented identities must not be able to tell them apart.
     */
    public function test_an_unknown_and_an_inactive_identity_are_indistinguishable(): void
    {
        $unknown = $this->completeSignIn();

        $this->existingUser(UserStatus::Inactive);
        $inactive = $this->completeSignIn();

        $this->assertSame($unknown->getStatusCode(), $inactive->getStatusCode());
        $this->assertSame(
            $unknown->headers->get('Location'),
            $inactive->headers->get('Location'),
            'An unknown identity and an inactive account land differently, which enumerates the directory.'
        );
    }

    public function test_an_active_user_signs_in_and_reaches_the_console(): void
    {
        $user = $this->existingUser();

        $this->completeSignIn()->assertRedirect(route('console.home'));

        $this->assertSame($user->id, session(EnsureSessionIsCurrent::SESSION_USER_ID));

        $this->get('/console')->assertOk();
    }

    /** Negative case 6. */
    public function test_a_mismatched_state_is_refused(): void
    {
        $this->existingUser();
        $this->entra->fakeEndpoints();

        $this->withSession([
            EntraProvider::SESSION_STATE => 'the-real-state',
            EntraProvider::SESSION_NONCE => 'test-nonce',
            EntraProvider::SESSION_VERIFIER => 'verifier',
        ])->get('/auth/microsoft/callback?code=abc&state=a-forged-state')
            ->assertRedirect(route('auth.sign-in-unavailable'));

        $this->assertNull(session(EnsureSessionIsCurrent::SESSION_USER_ID));
    }

    /**
     * A callback with no session state at all - the shape a CSRF attempt takes,
     * since the attacker cannot write to the victim's session.
     */
    public function test_a_callback_without_session_state_is_refused(): void
    {
        $this->entra->fakeEndpoints();

        $this->get('/auth/microsoft/callback?code=abc&state=anything')
            ->assertRedirect(route('auth.sign-in-unavailable'));
    }

    /** Negative case 8: the absolute lifetime Laravel does not provide. */
    public function test_a_session_beyond_the_absolute_lifetime_is_refused(): void
    {
        $user = $this->existingUser();

        $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->subHours(13)->toIso8601String(),
        ])->get('/console')->assertRedirect(route('auth.session-expired'));
    }

    public function test_a_session_within_the_absolute_lifetime_is_served(): void
    {
        $user = $this->existingUser();

        $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->subHours(11)->toIso8601String(),
        ])->get('/console')->assertOk();
    }

    /** D-10: revocation takes effect on the next protected request. */
    public function test_a_user_deactivated_mid_session_is_refused_on_the_next_request(): void
    {
        $user = $this->existingUser();

        $session = [
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ];

        $this->withSession($session)->get('/console')->assertOk();

        $user->update(['status' => UserStatus::Inactive]);

        $this->withSession($session)->get('/console')->assertRedirect(route('auth.account-inactive'));
    }

    /**
     * Negative case 11. Asserted against the authorisation boundary, not
     * against an empty result set: in P1-00 there is no business data to
     * request, so a test that merely found nothing would pass for the wrong
     * reason and keep passing after the boundary was removed.
     */
    public function test_a_system_administrator_receives_no_business_domain_access(): void
    {
        $admin = $this->existingUser(role: PlatformRole::SystemAdministrator);

        $response = $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $admin->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ])->get('/console');

        $response->assertOk();

        // The role exists and is readable...
        $this->assertTrue($admin->fresh()->isSystemAdministrator());

        // ...and confers nothing. There is no entitlement surface at all, and
        // no helper that could be mistaken for one.
        $this->assertFalse(method_exists($admin, 'domains'));
        $this->assertFalse(method_exists($admin, 'scopes'));
        $this->assertFalse(method_exists($admin, 'entitlements'));
        $this->assertFalse(method_exists($admin, 'can'));

        // ...and no business domain is REACHABLE. D-19 made the approved roadmap
        // visible, so "Sales Intelligence" is now a label on the screen. The
        // security claim was never about the word: it is that administration
        // confers no business-domain access. Asserting the absence of the word
        // would now be satisfied by deleting a label, which proves nothing, so
        // the claim is stated as reachability instead.
        //
        // Mutation: give any business-domain entry a route in ApprovedMenu.
        //
        // P1-02 adds a SECOND reachable entry, and it is the same kind of thing
        // as the first: Identity & SSO is System Administration, it reports on
        // the front door, and reading it grants no business-domain access
        // either. The claim under test is unchanged - the expected set is
        // simply the delivered set, and it is still exhaustive.
        $areas = $response->viewData('page')['props']['productAreas'] ?? [];

        $this->assertNotSame([], $areas, 'No navigation was rendered, so this test proves nothing.');

        $reachable = [];
        $walk = function (array $nodes) use (&$walk, &$reachable): void {
            foreach ($nodes as $node) {
                if ($node['route'] !== null) {
                    $reachable[$node['label']] = $node['route'];
                }

                $walk($node['children']);
            }
        };

        foreach ($areas as $area) {
            $walk($area['nodes']);
        }

        $this->assertSame(
            [
                'Organisation' => '/console/organisation',
                'Users & Groups' => '/console/people/users',
                'Identity & SSO' => '/console/identity',
            ],
            $reachable,
            'A System Administrator was offered a destination beyond Organisation. The role '
            .'describes the platform; it grants no business-domain capability.'
        );

        // Nor by URL. No route exists anywhere for a business domain, so the
        // menu is not the only thing standing between the role and the data.
        foreach (['sales', 'finance', 'people', 'executive', 'operations'] as $domain) {
            foreach (['/'.$domain, '/console/'.$domain, '/console/'.$domain.'-intelligence'] as $guess) {
                $this->assertNotSame(
                    200,
                    $this->withSession([
                        EnsureSessionIsCurrent::SESSION_USER_ID => $admin->id,
                        EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
                    ])->get($guess)->getStatusCode(),
                    "[{$guess}] served a business-domain screen to a System Administrator."
                );
            }
        }
    }

    public function test_logout_destroys_the_session_and_lands_on_signed_out(): void
    {
        $user = $this->existingUser();

        $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ])->post('/auth/logout')->assertRedirect(route('auth.signed-out'));

        $this->get('/console')->assertRedirect(route('entry'));
    }

    public function test_logout_refuses_a_get_request(): void
    {
        $this->get('/auth/logout')->assertStatus(405);
    }

    private function existingUser(
        UserStatus $status = UserStatus::Active,
        ?PlatformRole $role = null,
    ): User {
        return User::query()->create([
            'provider' => 'microsoft',
            'external_subject' => '33333333-3333-3333-3333-333333333333',
            'tenant_id' => EntraTokenFactory::TENANT,
            'email' => 'person@example.test',
            'display_name' => 'Test Person',
            'status' => $status,
            'platform_role' => $role,
        ]);
    }

    private function completeSignIn(array $claimOverrides = [])
    {
        $this->entra->fakeEndpoints($this->entra->token($claimOverrides));

        return $this->withSession([
            EntraProvider::SESSION_STATE => 'the-state',
            EntraProvider::SESSION_NONCE => 'test-nonce',
            EntraProvider::SESSION_VERIFIER => 'verifier',
        ])->get('/auth/microsoft/callback?code=abc&state=the-state');
    }
}
