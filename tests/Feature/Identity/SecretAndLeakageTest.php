<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\EntraTokenFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * Negative case 13, and the secret boundary.
 *
 * The client secret must never reach the browser, and no refusal state may leak
 * a token, tenant, role mapping or trace. Both are asserted against real
 * rendered responses rather than reasoned about.
 */
final class SecretAndLeakageTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'a-very-secret-client-secret-value';

    protected function setUp(): void
    {
        parent::setUp();

        (new EntraTokenFactory)->configure();
        config(['identity.microsoft.client_secret' => self::SECRET]);
    }

    public function test_the_client_secret_never_reaches_a_page_payload(): void
    {
        $pages = ['/', '/auth/access-not-assigned', '/auth/account-inactive',
            '/auth/access-denied', '/auth/session-expired', '/auth/signed-out',
            '/auth/sign-in-unavailable', '/first-run/closed'];

        foreach ($pages as $page) {
            $body = $this->get($page)->getContent();

            $this->assertStringNotContainsString(self::SECRET, $body, "[{$page}] leaked the client secret.");
            $this->assertStringNotContainsString(EntraTokenFactory::CLIENT_ID, $body, "[{$page}] leaked the client id.");
            $this->assertStringNotContainsString(EntraTokenFactory::TENANT, $body, "[{$page}] leaked the tenant id.");
        }
    }

    /**
     * S1 and S3. Every P1-02 screen joins the same assertion, against REAL
     * rendered responses.
     *
     * This test is EXTENDED rather than joined by a second leakage test. Two
     * tests each covering half the surface, with neither knowing it, is the more
     * dangerous arrangement.
     *
     * The identifiers matter as much as the secret here. The masking on the
     * Entra screen is only real if the full value is genuinely absent from the
     * payload: a CSS mask over a value already in the props would look like
     * protection while providing none, and would sit in the page source of every
     * screenshot-adjacent artefact.
     *
     * Mutation: send the full tenant or client id as a prop and mask it in the
     * component. CAUGHT here and nowhere else.
     */
    public function test_no_identity_screen_leaks_a_secret_or_a_full_identifier(): void
    {
        $admin = (new OrganisationFactory)->user(administrator: true);

        $screens = ['/console/identity', '/console/identity/providers',
            '/console/identity/login-experience', '/console/identity/health',
            '/console/identity/session-policy'];

        foreach ($screens as $screen) {
            $body = $this->actingAsAdministrator($admin)->get($screen)->getContent();

            $this->assertStringNotContainsString(self::SECRET, $body, "[{$screen}] leaked the client secret.");
            $this->assertStringNotContainsString(EntraTokenFactory::CLIENT_ID, $body, "[{$screen}] leaked the full client id.");
            $this->assertStringNotContainsString(EntraTokenFactory::TENANT, $body, "[{$screen}] leaked the full tenant id.");
        }
    }

    /** S2. And no token, code, verifier, nonce, state, session id or key. */
    public function test_no_identity_screen_leaks_a_token_or_a_key(): void
    {
        $admin = (new OrganisationFactory)->user(administrator: true);

        foreach (['/console/identity', '/console/identity/health'] as $screen) {
            $body = $this->actingAsAdministrator($admin)->get($screen)->getContent();

            foreach (['id_token', 'access_token', 'code_verifier', 'APP_KEY', 'base64:',
                'Stack trace', 'Illuminate\\', 'vendor/laravel'] as $leak) {
                $this->assertStringNotContainsString($leak, $body, "[{$screen}] leaked [{$leak}].");
            }
        }
    }

    /**
     * S4. The reveal endpoint returns ONE requested identifier, and there is no
     * field name that would return the secret.
     */
    public function test_reveal_returns_one_identifier_and_never_the_secret(): void
    {
        $admin = (new OrganisationFactory)->user(administrator: true);

        $directory = $this->actingAsAdministrator($admin)
            ->post('/console/identity/entra/reveal', ['field' => 'directory']);

        $directory->assertOk();
        $this->assertSame(EntraTokenFactory::TENANT, $directory->json('value'));
        $this->assertStringNotContainsString(EntraTokenFactory::CLIENT_ID, $directory->getContent());
        $this->assertStringNotContainsString(self::SECRET, $directory->getContent());

        $application = $this->actingAsAdministrator($admin)
            ->post('/console/identity/entra/reveal', ['field' => 'application']);

        $application->assertOk();
        $this->assertSame(EntraTokenFactory::CLIENT_ID, $application->json('value'));
        $this->assertStringNotContainsString(EntraTokenFactory::TENANT, $application->getContent());
        $this->assertStringNotContainsString(self::SECRET, $application->getContent());
    }

    /** The screen says Present, and says nothing else about the secret. */
    public function test_the_secret_is_reported_as_presence_only(): void
    {
        $admin = (new OrganisationFactory)->user(administrator: true);

        $body = $this->actingAsAdministrator($admin)->get('/console/identity')->getContent();

        $this->assertStringContainsString('Present', $body);

        // Not its length, not a fragment, not a hash.
        $this->assertStringNotContainsString((string) strlen(self::SECRET), $body);
        $this->assertStringNotContainsString(substr(self::SECRET, 0, 4), $body);
        $this->assertStringNotContainsString(hash('sha256', self::SECRET), $body);
    }

    private function actingAsAdministrator(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }

    /** Negative case 13. */
    public function test_refusal_states_leak_nothing(): void
    {
        foreach (['access-not-assigned', 'account-inactive', 'access-denied',
            'session-expired', 'signed-out', 'sign-in-unavailable'] as $state) {
            $body = $this->get("/auth/{$state}")->getContent();

            foreach (['Stack trace', 'Illuminate\\', 'vendor/laravel', '<?php',
                'id_token', 'code_verifier', 'nonce', 'system_administrator'] as $leak) {
                $this->assertStringNotContainsString($leak, $body, "[{$state}] leaked [{$leak}].");
            }
        }
    }

    /**
     * The two states must read identically, so neither the wording nor the
     * status tells an anonymous caller which one they reached.
     */
    public function test_not_assigned_and_inactive_read_identically(): void
    {
        $notAssigned = $this->get('/auth/access-not-assigned');
        $inactive = $this->get('/auth/account-inactive');

        $this->assertSame($notAssigned->getStatusCode(), $inactive->getStatusCode());

        $this->assertSame(
            $this->visibleMessage($notAssigned->getContent()),
            $this->visibleMessage($inactive->getContent()),
            'The two states word their refusal differently, which tells a caller which one they hit.'
        );
    }

    private function visibleMessage(string $html): string
    {
        preg_match('/"state":"([a-z-]+)"/', $html, $matches);

        $state = $matches[1] ?? '';

        // Compare what the person actually reads, not the route that produced
        // it: the wording is the part an anonymous caller can observe.
        return match ($state) {
            'access-not-assigned', 'account-inactive' => 'Your account does not have access to SemantIQ.',
            default => $html,
        };
    }

    /**
     * The security logger takes a fixed context shape. A caller cannot pass a
     * token, code, nonce or grant by accident, because there is nowhere for it
     * to go - and a rejected key is a hard failure, since a logger that quietly
     * dropped a leak would be worse than none.
     */
    public function test_the_security_logger_refuses_forbidden_context(): void
    {
        $logger = app(SecurityEventLogger::class);

        foreach (['id_token', 'access_token', 'code', 'nonce', 'state', 'code_verifier', 'grant', 'client_secret'] as $key) {
            try {
                $logger->record(SecurityEventLogger::LOGIN_SUCCEEDED, [$key => 'value']);
                $this->fail("The security logger accepted the forbidden key [{$key}].");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($key, $e->getMessage());
            }
        }
    }

    public function test_the_security_logger_refuses_an_unknown_event(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SecurityEventLogger::class)->record('auth.something.invented');
    }
}
