<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Microsoft Entra ID sign-in flow.
 *
 * Microsoft is faked, so these tests are about our half of the exchange: that
 * state is single use, that a nonce from another session is refused, that PKCE
 * is actually sent, and that a failure never leaks a reason to the browser.
 * Those are the properties an attacker probes, and none of them is visible by
 * reading the happy path.
 */
class MicrosoftSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.microsoft', [
            'tenant' => 'tenant-guid',
            'client_id' => 'client-guid',
            'client_secret' => 'shh',
            'redirect' => 'http://localhost/auth/microsoft/callback',
        ]);
    }

    /**
     * An ID token shaped the way Microsoft returns one. Unsigned: the flow
     * decodes the payload rather than verifying it, for the reason set out in
     * the controller.
     */
    private function idToken(array $claims): string
    {
        $encode = fn (array $part): string => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

        return $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($claims).'.signature';
    }

    private function fakeMicrosoft(string $nonce, array $overrides = []): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'access-token',
                'id_token' => $this->idToken(array_merge([
                    'oid' => 'object-id-1',
                    'tid' => 'tenant-guid',
                    'nonce' => $nonce,
                    'name' => 'Salil Mhatre',
                    'preferred_username' => 'salil@lithan.com',
                ], $overrides)),
            ]),
            'graph.microsoft.com/*' => Http::response([
                'displayName' => 'Salil Mhatre',
                'mail' => 'salil@lithan.com',
                'userPrincipalName' => 'salil@lithan.com',
            ]),
        ]);
    }

    /**
     * Start the flow and return the state and nonce it put in the session.
     *
     * @return array{state: string, nonce: string}
     */
    private function start(): array
    {
        $this->post('/sign-in/microsoft')->assertRedirect();

        return [
            'state' => session('microsoft.state'),
            'nonce' => session('microsoft.nonce'),
        ];
    }

    #[Test]
    public function starting_a_sign_in_redirects_to_entra_with_pkce_and_a_single_use_state(): void
    {
        $response = $this->post('/sign-in/microsoft');

        $target = $response->headers->get('Location');

        $this->assertStringStartsWith(
            'https://login.microsoftonline.com/tenant-guid/oauth2/v2.0/authorize',
            $target,
        );

        parse_str(parse_url($target, PHP_URL_QUERY), $query);

        $this->assertSame('code', $query['response_type']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty($query['code_challenge']);
        $this->assertSame(session('microsoft.state'), $query['state']);
        $this->assertSame(session('microsoft.nonce'), $query['nonce']);

        // The verifier itself must never be in the URL: putting it there would
        // defeat the entire point of PKCE.
        $this->assertArrayNotHasKey('code_verifier', $query);
    }

    #[Test]
    public function the_challenge_is_the_s256_hash_of_the_stored_verifier(): void
    {
        $target = $this->post('/sign-in/microsoft')->headers->get('Location');
        parse_str(parse_url($target, PHP_URL_QUERY), $query);

        $expected = rtrim(strtr(
            base64_encode(hash('sha256', session('microsoft.code_verifier'), true)),
            '+/',
            '-_',
        ), '=');

        $this->assertSame($expected, $query['code_challenge']);
    }

    #[Test]
    public function a_successful_sign_in_creates_the_account(): void
    {
        ['state' => $state, 'nonce' => $nonce] = $this->start();
        $this->fakeMicrosoft($nonce);

        $this->get("/auth/microsoft/callback?code=auth-code&state={$state}")
            ->assertRedirect('/');

        $user = User::query()->where('email', 'salil@lithan.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('object-id-1', $user->entra_object_id);
        $this->assertSame('tenant-guid', $user->entra_tenant_id);
        $this->assertTrue($user->isFederated());
        $this->assertNull($user->password);
        $this->assertNotNull($user->last_signed_in_at);
    }

    #[Test]
    public function the_code_is_exchanged_with_the_verifier_and_never_the_challenge(): void
    {
        ['state' => $state, 'nonce' => $nonce] = $this->start();
        $verifier = session('microsoft.code_verifier');
        $this->fakeMicrosoft($nonce);

        $this->get("/auth/microsoft/callback?code=auth-code&state={$state}");

        Http::assertSent(function ($request) use ($verifier) {
            if (! str_contains($request->url(), '/oauth2/v2.0/token')) {
                return false;
            }

            return $request['code_verifier'] === $verifier
                && $request['grant_type'] === 'authorization_code'
                && $request['code'] === 'auth-code';
        });
    }

    #[Test]
    public function signing_in_again_updates_the_account_rather_than_duplicating_it(): void
    {
        foreach ([1, 2] as $ignored) {
            ['state' => $state, 'nonce' => $nonce] = $this->start();
            $this->fakeMicrosoft($nonce);
            $this->get("/auth/microsoft/callback?code=auth-code&state={$state}");
            $this->post('/sign-out');
        }

        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function an_account_is_matched_by_object_id_even_when_the_address_changed(): void
    {
        $existing = User::query()->create(['name' => 'Old Name', 'email' => 'old@lithan.com']);
        $existing->forceFill(['entra_object_id' => 'object-id-1'])->save();

        ['state' => $state, 'nonce' => $nonce] = $this->start();
        $this->fakeMicrosoft($nonce);

        $this->get("/auth/microsoft/callback?code=auth-code&state={$state}");

        // The same person, renamed - not a second account.
        $this->assertSame(1, User::query()->count());
        $this->assertSame('salil@lithan.com', $existing->fresh()->email);
    }

    #[Test]
    public function an_existing_local_account_is_adopted_by_the_directory(): void
    {
        $local = User::query()->create([
            'name' => 'Salil Mhatre',
            'email' => 'salil@lithan.com',
            'password' => Hash::make('local-password'),
        ]);

        ['state' => $state, 'nonce' => $nonce] = $this->start();
        $this->fakeMicrosoft($nonce);

        $this->get("/auth/microsoft/callback?code=auth-code&state={$state}");

        // Matching on the address is what stops this colliding on the unique
        // email and failing the first time someone moves to Microsoft sign-in.
        $this->assertSame(1, User::query()->count());
        $this->assertSame('object-id-1', $local->fresh()->entra_object_id);
    }

    #[Test]
    public function a_callback_whose_state_does_not_match_is_refused(): void
    {
        $this->start();
        $this->fakeMicrosoft('whatever');

        $this->get('/auth/microsoft/callback?code=auth-code&state=forged')
            ->assertRedirect('/sign-in')
            ->assertSessionHasErrors('form');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    #[Test]
    public function a_replayed_callback_is_refused_because_state_is_consumed_once(): void
    {
        ['state' => $state, 'nonce' => $nonce] = $this->start();
        $this->fakeMicrosoft($nonce);

        $this->get("/auth/microsoft/callback?code=auth-code&state={$state}")->assertRedirect('/');
        $this->post('/sign-out');

        // The same link again: the session value was pulled the first time.
        $this->get("/auth/microsoft/callback?code=auth-code&state={$state}")
            ->assertRedirect('/sign-in')
            ->assertSessionHasErrors('form');

        $this->assertGuest();
    }

    #[Test]
    public function an_id_token_whose_nonce_does_not_match_is_refused(): void
    {
        ['state' => $state] = $this->start();

        // A token minted for a different session.
        $this->fakeMicrosoft('nonce-from-another-session');

        $this->get("/auth/microsoft/callback?code=auth-code&state={$state}")
            ->assertRedirect('/sign-in')
            ->assertSessionHasErrors('form');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function a_sign_in_declined_at_microsoft_is_reported_without_leaking_the_reason(): void
    {
        ['state' => $state] = $this->start();

        // Followed through to the rendered page, because what matters is what
        // reaches the browser, not what sits in an error bag on the way there.
        $response = $this->followingRedirects()->get(
            "/auth/microsoft/callback?state={$state}&error=access_denied"
            .'&error_description=AADSTS65004%3A+User+declined+consent+for+tenant+policy+X'
        );

        $response->assertOk()
            ->assertSee('Sign-in was not completed at Microsoft.')
            // Microsoft's description can name internal tenant policy. The
            // browser gets a sentence; the detail goes to the log.
            ->assertDontSee('AADSTS65004')
            ->assertDontSee('tenant policy');

        $this->assertGuest();
    }

    #[Test]
    public function a_refused_token_exchange_does_not_sign_anyone_in(): void
    {
        ['state' => $state] = $this->start();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(
                ['error' => 'invalid_client', 'error_description' => 'secret expired'],
                401,
            ),
        ]);

        $response = $this->get("/auth/microsoft/callback?code=auth-code&state={$state}");

        $response->assertRedirect('/sign-in')->assertSessionHasErrors('form');

        $this->assertStringNotContainsString('invalid_client', session('errors')->first('form'));
        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function the_address_comes_from_graph_when_the_claims_are_unusable(): void
    {
        ['state' => $state, 'nonce' => $nonce] = $this->start();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'access-token',
                'id_token' => $this->idToken([
                    'oid' => 'object-id-2',
                    'tid' => 'tenant-guid',
                    'nonce' => $nonce,
                    // A guest account's username is their home-tenant address.
                    'preferred_username' => 'guest_elsewhere.com#EXT#@tenant.onmicrosoft.com',
                ]),
            ]),
            'graph.microsoft.com/*' => Http::response([
                'displayName' => 'Guest Person',
                'mail' => 'guest@elsewhere.com',
            ]),
        ]);

        $this->get("/auth/microsoft/callback?code=auth-code&state={$state}")->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'guest@elsewhere.com']);
    }

    #[Test]
    public function a_profile_without_an_object_id_is_refused(): void
    {
        ['state' => $state, 'nonce' => $nonce] = $this->start();
        $this->fakeMicrosoft($nonce, ['oid' => null]);

        $this->get("/auth/microsoft/callback?code=auth-code&state={$state}")
            ->assertRedirect('/sign-in')
            ->assertSessionHasErrors('form');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function the_flow_says_so_when_entra_is_not_configured(): void
    {
        config()->set('services.microsoft.client_secret', null);

        $response = $this->post('/sign-in/microsoft');

        // An explanation, not a bounce to a Microsoft error page.
        $response->assertRedirect('/sign-in')->assertSessionHasErrors('form');
        $this->assertStringContainsString('not configured', session('errors')->first('form'));
    }
}
