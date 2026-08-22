<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Support\Tenancy\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SignInTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Configure a complete, fake Entra ID registration.
     *
     * The values are obvious placeholders. No real credential appears in this
     * repository, in this suite, or in its output.
     */
    private function configureEntra(): void
    {
        config()->set('services.microsoft', [
            'tenant' => 'test-tenant-id',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect' => 'https://semantiq.test/auth/microsoft/callback',
        ]);
    }

    #[Test]
    public function the_sign_in_screen_renders(): void
    {
        $this->get('/sign-in')
            ->assertOk()
            ->assertSee('Sign in with Microsoft')
            ->assertSee('CLaaS2SaaS');
    }

    #[Test]
    public function the_sign_in_screen_says_so_when_entra_is_not_configured(): void
    {
        config()->set('services.microsoft', [
            'tenant' => null, 'client_id' => null, 'client_secret' => null, 'redirect' => null,
        ]);

        $this->get('/sign-in')
            ->assertOk()
            ->assertSee('Sign-in is unavailable until an administrator registers this environment')
            ->assertSee('disabled', escape: false);
    }

    #[Test]
    public function starting_a_sign_in_fails_closed_when_entra_is_not_configured(): void
    {
        config()->set('services.microsoft', [
            'tenant' => null, 'client_id' => null, 'client_secret' => null, 'redirect' => null,
        ]);

        $this->from('/sign-in')
            ->post('/auth/microsoft/redirect')
            ->assertRedirect('/sign-in')
            ->assertSessionHas('status.title', 'Microsoft sign-in is not configured yet');
    }

    #[Test]
    public function starting_a_sign_in_redirects_to_entra_with_pkce_and_a_single_use_state(): void
    {
        $this->configureEntra();

        $response = $this->post('/auth/microsoft/redirect');

        $response->assertRedirectContains('https://login.microsoftonline.com/test-tenant-id/oauth2/v2.0/authorize');

        $location = $response->headers->get('Location');
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        $this->assertSame('code', $query['response_type']);
        $this->assertSame('test-client-id', $query['client_id']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty($query['code_challenge']);
        $this->assertNotEmpty($query['state']);
        $this->assertNotEmpty($query['nonce']);

        // The client secret must never reach a URL the browser can see.
        $this->assertStringNotContainsString('test-client-secret', (string) $location);

        // The challenge is a hash, so the verifier itself must not appear either.
        $this->assertSame(session('microsoft.state'), $query['state']);
        $this->assertStringNotContainsString(session('microsoft.code_verifier'), (string) $location);
    }

    #[Test]
    public function the_callback_rejects_a_state_that_does_not_match_the_session(): void
    {
        $this->configureEntra();
        Http::fake();

        $this->withSession(['microsoft.state' => 'the-real-state'])
            ->get('/auth/microsoft/callback?code=abc&state=a-forged-state')
            ->assertRedirect('/sign-in')
            ->assertSessionHas('status.title', 'Sign-in could not be verified');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    #[Test]
    public function the_callback_rejects_a_replay_because_state_is_consumed_once(): void
    {
        $this->configureEntra();
        Http::fake();

        // No state in the session at all, which is what a replayed callback finds.
        $this->get('/auth/microsoft/callback?code=abc&state=some-state')
            ->assertRedirect('/sign-in')
            ->assertSessionHas('status.title', 'Sign-in could not be verified');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    #[Test]
    public function the_callback_reports_a_sign_in_declined_at_microsoft(): void
    {
        $this->configureEntra();

        $this->withSession(['microsoft.state' => 'st'])
            ->get('/auth/microsoft/callback?error=access_denied&state=st')
            ->assertRedirect('/sign-in')
            ->assertSessionHas('status.title', 'Sign-in was not completed');

        $this->assertGuest();
    }

    #[Test]
    public function the_callback_rejects_an_id_token_whose_nonce_does_not_match(): void
    {
        $this->configureEntra();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'token',
                'id_token' => $this->idTokenWithNonce('a-different-nonce'),
            ]),
        ]);

        $this->withSession(['microsoft.state' => 'st', 'microsoft.nonce' => 'the-real-nonce', 'microsoft.code_verifier' => 'v'])
            ->get('/auth/microsoft/callback?code=abc&state=st')
            ->assertRedirect('/sign-in')
            ->assertSessionHas('status.title', 'Sign-in could not be verified');

        $this->assertGuest();
    }

    #[Test]
    public function a_successful_sign_in_creates_the_account_as_a_viewer(): void
    {
        $this->configureEntra();
        $this->fakeSuccessfulEntra('newcomer@example.test', 'New Comer', 'object-id-1');

        $this->withSession($this->pendingSignIn())
            ->get('/auth/microsoft/callback?code=abc&state=st')
            ->assertRedirect('/');

        $user = $this->unscoped(fn () => User::query()->where('email', 'newcomer@example.test')->sole());

        $this->assertAuthenticatedAs($user);
        $this->assertSame(Role::Viewer, $user->role);
        $this->assertSame('object-id-1', $user->entra_object_id);
        $this->assertNull($user->password);
        $this->assertNotNull($user->last_signed_in_at);
    }

    #[Test]
    public function the_bootstrap_address_is_made_a_system_administrator(): void
    {
        $this->configureEntra();
        config()->set('semantiq.bootstrap_admins', ['salil@lithan.com']);
        $this->fakeSuccessfulEntra('salil@lithan.com', 'Salil', 'object-id-owner');

        $this->withSession($this->pendingSignIn())
            ->get('/auth/microsoft/callback?code=abc&state=st')
            ->assertRedirect('/');

        $user = $this->unscoped(fn () => User::query()->where('email', 'salil@lithan.com')->sole());

        $this->assertSame(Role::SystemAdmin, $user->role);
        $this->assertTrue($user->isSystemAdmin());
    }

    #[Test]
    public function the_bootstrap_list_matches_regardless_of_case(): void
    {
        $this->configureEntra();
        config()->set('semantiq.bootstrap_admins', ['Salil@Lithan.COM']);
        $this->fakeSuccessfulEntra('SALIL@lithan.com', 'Salil', 'object-id-owner');

        $this->withSession($this->pendingSignIn())->get('/auth/microsoft/callback?code=abc&state=st');

        $this->assertSame(Role::SystemAdmin, $this->unscoped(fn () => User::query()->sole())->role);
    }

    #[Test]
    public function the_bootstrap_list_promotes_but_never_demotes(): void
    {
        $this->configureEntra();
        config()->set('semantiq.bootstrap_admins', []);

        // An administrator who is no longer on the bootstrap list keeps their role.
        User::query()->create([
            'name' => 'Salil', 'email' => 'salil@lithan.com', 'password' => null,
        ])->forceFill(['role' => Role::SystemAdmin, 'entra_object_id' => 'object-id-owner'])->save();

        $this->fakeSuccessfulEntra('salil@lithan.com', 'Salil', 'object-id-owner');

        $this->withSession($this->pendingSignIn())->get('/auth/microsoft/callback?code=abc&state=st');

        $this->assertSame(Role::SystemAdmin, $this->unscoped(fn () => User::query()->sole())->role);
    }

    #[Test]
    public function signing_in_again_updates_the_existing_account_rather_than_duplicating_it(): void
    {
        $this->configureEntra();
        $this->fakeSuccessfulEntra('person@example.test', 'Renamed Person', 'object-id-2');

        User::query()->create([
            'name' => 'Old Name', 'email' => 'person@example.test', 'password' => null,
        ])->forceFill(['entra_object_id' => 'object-id-2', 'role' => Role::Admin])->save();

        $this->withSession($this->pendingSignIn())->get('/auth/microsoft/callback?code=abc&state=st');

        $this->assertSame(1, $this->unscoped(fn () => User::query()->count()));
        $user = $this->unscoped(fn () => User::query()->sole());
        $this->assertSame('Renamed Person', $user->name);
        // An existing role survives a later sign-in.
        $this->assertSame(Role::Admin, $user->role);
    }

    #[Test]
    public function an_account_is_matched_by_object_id_even_when_its_address_changed(): void
    {
        $this->configureEntra();
        $this->fakeSuccessfulEntra('new.address@example.test', 'Same Person', 'object-id-3');

        User::query()->create([
            'name' => 'Same Person', 'email' => 'old.address@example.test', 'password' => null,
        ])->forceFill(['entra_object_id' => 'object-id-3'])->save();

        $this->withSession($this->pendingSignIn())->get('/auth/microsoft/callback?code=abc&state=st');

        $this->assertSame(1, $this->unscoped(fn () => User::query()->count()));
        $this->assertSame('new.address@example.test', $this->unscoped(fn () => User::query()->sole())->email);
    }

    #[Test]
    public function a_refused_token_exchange_does_not_sign_anyone_in(): void
    {
        $this->configureEntra();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->withSession($this->pendingSignIn())
            ->get('/auth/microsoft/callback?code=abc&state=st')
            ->assertRedirect('/sign-in')
            ->assertSessionHas('status.title', 'Sign-in could not be completed');

        $this->assertGuest();
        $this->assertSame(0, $this->unscoped(fn () => User::query()->count()));
    }

    #[Test]
    public function a_refused_graph_profile_does_not_sign_anyone_in(): void
    {
        $this->configureEntra();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'token',
                'id_token' => $this->idTokenWithNonce('the-nonce'),
            ]),
            'graph.microsoft.com/*' => Http::response([], 403),
        ]);

        $this->withSession($this->pendingSignIn())
            ->get('/auth/microsoft/callback?code=abc&state=st')
            ->assertRedirect('/sign-in')
            ->assertSessionHas('status.title', 'Your directory profile could not be read');

        $this->assertGuest();
        $this->assertSame(0, $this->unscoped(fn () => User::query()->count()));
    }

    #[Test]
    public function a_signed_in_person_is_sent_away_from_the_sign_in_screen(): void
    {
        $user = User::query()->create([
            'name' => 'Someone', 'email' => 'someone@example.test', 'password' => null,
        ]);

        $this->actingAs($user)->get('/sign-in')->assertRedirect('/');
    }

    #[Test]
    public function signing_out_ends_the_session(): void
    {
        $user = User::query()->create([
            'name' => 'Someone', 'email' => 'someone@example.test', 'password' => null,
        ]);

        $this->actingAs($user)
            ->post('/sign-out')
            ->assertRedirect('/sign-in');

        $this->assertGuest();
    }

    #[Test]
    public function signing_out_requires_being_signed_in(): void
    {
        $this->post('/sign-out')->assertRedirect();
        $this->assertGuest();
    }

    /**
     * Read the users table the way the sign-in flow does, outside the
     * organisation scope.
     *
     * A federated account is created before any organisation is assigned, so
     * asserting about it through the scoped default would assert about an
     * empty set and pass for the wrong reason.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function unscoped(callable $callback): mixed
    {
        return app(OrganisationContext::class)->withoutScoping($callback);
    }

    /**
     * The session state a callback expects to find mid-flow.
     *
     * @return array<string, string>
     */
    private function pendingSignIn(): array
    {
        return [
            'microsoft.state' => 'st',
            'microsoft.nonce' => 'the-nonce',
            'microsoft.code_verifier' => 'the-verifier',
        ];
    }

    /**
     * Fake a complete, successful round trip to Entra ID and Microsoft Graph.
     */
    private function fakeSuccessfulEntra(string $email, string $name, string $objectId): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'an-access-token',
                'id_token' => $this->idTokenWithNonce('the-nonce'),
            ]),
            'graph.microsoft.com/*' => Http::response([
                'id' => $objectId,
                'displayName' => $name,
                'mail' => $email,
                'userPrincipalName' => $email,
            ]),
        ]);
    }

    /**
     * An unsigned JWT carrying only the claim under test.
     *
     * Unsigned is faithful to what the controller reads: it takes the payload
     * from a token delivered over an authenticated TLS channel and never checks
     * a signature, so signing this fixture would test nothing extra.
     */
    private function idTokenWithNonce(string $nonce): string
    {
        $segment = static fn (array $data): string => rtrim(
            strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '='
        );

        return $segment(['alg' => 'RS256', 'typ' => 'JWT'])
            .'.'.$segment(['nonce' => $nonce, 'tid' => 'test-tenant-id'])
            .'.signature-not-checked';
    }
}
