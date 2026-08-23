<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Security\Enums\AuthenticationMode;
use App\Modules\Security\Support\SecurityPolicies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ControlsSecurityPolicy;
use Tests\TestCase;

/**
 * ADM-009 Authentication Policy.
 *
 * The point of these tests is that the policy CHANGES BEHAVIOUR. A screen that
 * stores an authentication mode which no sign-in path reads would pass a
 * storage test and protect nothing, so every test here drives a real sign-in
 * and asserts what the policy did to it.
 */
class AuthenticationPolicyTest extends TestCase
{
    use ControlsSecurityPolicy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.microsoft', [
            'tenant' => 'tenant-guid',
            'client_id' => 'client-guid',
            'client_secret' => 'shh',
            'redirect' => 'http://localhost/auth/microsoft/callback',
        ]);

        RateLimiter::clear('sign-in|local@example.test|127.0.0.1');
        RateLimiter::clear('sign-in|viewer@example.test|127.0.0.1');
    }

    /**
     * The form-level error message from a response, whatever shape the session
     * put it in.
     *
     * `session('errors')` comes back as a ViewErrorBag on some paths and as a
     * plain array on others, depending on whether the error was flashed by a
     * ValidationException or by `withErrors()` on a redirect. A test that
     * assumes one shape passes until somebody changes how a controller refuses.
     */
    private function formError(TestResponse $response): string
    {
        $errors = $response->getSession()->get('errors');

        if ($errors instanceof ViewErrorBag) {
            return (string) $errors->first('form');
        }

        if (is_array($errors)) {
            return (string) (($errors['form'][0] ?? $errors['form'] ?? ''));
        }

        return '';
    }

    private function localAccount(Role $role, string $email): User
    {
        $user = User::query()->create([
            'name' => 'Local Person',
            'email' => $email,
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $user->forceFill([
            'role' => $role,
            'authentication_source' => 'local',
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    /* ---- The credential form ------------------------------------------- */

    #[Test]
    public function the_default_mode_admits_a_local_system_administrator(): void
    {
        $this->localAccount(Role::SystemAdmin, 'local@example.test');

        $this->post('/sign-in', ['email' => 'local@example.test', 'password' => 'correct-horse-battery'])
            ->assertRedirect('/');

        $this->assertAuthenticated();
    }

    #[Test]
    public function the_default_mode_refuses_a_local_business_user_with_the_same_message(): void
    {
        // "Require Entra for business users" is what this actually means, and
        // the refusal has to be indistinguishable from a wrong password:
        // nobody has proved anything at this point, so naming the reason would
        // confirm the address belongs to a real person here. SEC-DEC-027.
        $this->localAccount(Role::Viewer, 'viewer@example.test');

        $refused = $this->from('/sign-in')->post('/sign-in', [
            'email' => 'viewer@example.test', 'password' => 'correct-horse-battery',
        ]);

        $wrongPassword = $this->from('/sign-in')->post('/sign-in', [
            'email' => 'viewer@example.test', 'password' => 'wrong',
        ]);

        $refused->assertSessionHasErrors('form');
        $this->assertGuest();

        $this->assertSame($this->formError($wrongPassword), $this->formError($refused));
        $this->assertNotSame('', $this->formError($refused));

        // The real reason IS recorded, where an administrator can see it.
        $this->assertTrue(
            AuditEvent::query()
                ->where('action', 'auth.login.failed')
                ->get()
                ->contains(fn (AuditEvent $event): bool => str_contains((string) $event->reason, 'local System Administrator')),
        );
    }

    #[Test]
    public function local_only_mode_admits_a_business_user(): void
    {
        // The guard must refuse the mode, not the feature. Without this the
        // suite would pass just as well with the credential form broken.
        $this->withSecurityPolicy('sign_in.mode', AuthenticationMode::LocalOnly->value);
        $this->localAccount(Role::Viewer, 'viewer@example.test');

        $this->post('/sign-in', ['email' => 'viewer@example.test', 'password' => 'correct-horse-battery'])
            ->assertRedirect('/');

        $this->assertAuthenticated();
    }

    #[Test]
    public function federated_only_mode_removes_the_credential_form_and_refuses_the_route(): void
    {
        $this->withSecurityPolicy('sign_in.mode', AuthenticationMode::FederatedOnly->value);
        $this->localAccount(Role::SystemAdmin, 'local@example.test');

        // Absent from the screen, not disabled on it.
        $this->get('/sign-in')
            ->assertOk()
            ->assertSee('Sign in with Microsoft')
            ->assertDontSee('Work email');

        // And the route refuses independently: a control that is only hidden
        // is not an access control.
        $this->from('/sign-in')
            ->post('/sign-in', ['email' => 'local@example.test', 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors('form');

        $this->assertGuest();
    }

    #[Test]
    public function local_only_mode_removes_the_microsoft_button(): void
    {
        $this->withSecurityPolicy('sign_in.mode', AuthenticationMode::LocalOnly->value);

        $this->get('/sign-in')
            ->assertOk()
            ->assertSee('Work email')
            ->assertDontSee('Sign in with Microsoft');

        $this->post('/sign-in/microsoft')->assertRedirect(route('sign-in'));
    }

    #[Test]
    public function turning_break_glass_off_closes_the_credential_form(): void
    {
        $this->withSecurityPolicy('sign_in.allow_local_admin', false);
        $this->localAccount(Role::SystemAdmin, 'local@example.test');

        $this->get('/sign-in')->assertOk()->assertDontSee('Work email');

        $this->from('/sign-in')
            ->post('/sign-in', ['email' => 'local@example.test', 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors('form');

        $this->assertGuest();
    }

    /* ---- Threshold and lockout ----------------------------------------- */

    #[Test]
    public function the_attempt_threshold_comes_from_policy_and_not_from_a_constant(): void
    {
        // ADM-009 names "Failed Login Threshold" as a setting. Before gate 3 it
        // was a private constant in the controller, which is not a setting.
        $this->withSecurityPolicy('sign_in.failed_attempt_threshold', 3);
        $this->localAccount(Role::SystemAdmin, 'local@example.test');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->from('/sign-in')->post('/sign-in', [
                'email' => 'local@example.test', 'password' => 'wrong',
            ]);
        }

        $locked = $this->from('/sign-in')->post('/sign-in', [
            'email' => 'local@example.test', 'password' => 'correct-horse-battery',
        ]);

        $locked->assertInvalid(['form' => 'Too many sign-in attempts']);

        $this->assertGuest();
    }

    /* ---- The Microsoft path -------------------------------------------- */

    private function completeMicrosoftSignIn(string $email, string $tenant = 'tenant-guid'): TestResponse
    {
        $encode = fn (array $part): string => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');

        $this->get('/sign-in');
        $this->post('/sign-in/microsoft');

        $state = session('microsoft.state');
        $nonce = session('microsoft.nonce');

        $idToken = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
            'oid' => 'object-'.md5($email),
            'tid' => $tenant,
            'nonce' => $nonce,
            'preferred_username' => $email,
            'name' => 'Federated Person',
        ]).'.signature';

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'id_token' => $idToken,
                'access_token' => 'access-token',
            ]),
            'graph.microsoft.com/*' => Http::response([
                'mail' => $email,
                'displayName' => 'Federated Person',
            ]),
        ]);

        return $this->get('/auth/microsoft/callback?state='.$state.'&code=auth-code');
    }

    #[Test]
    public function an_unknown_directory_account_is_refused_when_auto_create_is_off(): void
    {
        // OFF is the default, and it is the safer one: a directory can contain
        // every employee, every contractor and every guest of every partner,
        // and "authenticated" is not the same question as "should be able to
        // use this".
        $this->assertFalse(app(SecurityPolicies::class)->enabled('sign_in.auto_create_users'));

        $this->completeMicrosoftSignIn('stranger@contoso.test')->assertRedirect(route('sign-in'));

        $this->assertGuest();
        $this->assertNull(User::query()->where('email', 'stranger@contoso.test')->first());

        $this->assertTrue(
            AuditEvent::query()
                ->where('action', 'auth.login.failed')
                ->get()
                ->contains(fn (AuditEvent $e): bool => str_contains((string) $e->reason, 'automatic account creation is off')),
        );
    }

    #[Test]
    public function a_tenant_outside_the_allow_list_is_refused_before_any_account_is_touched(): void
    {
        $this->withSecurityPolicy('sign_in.allowed_tenant_id', '11111111-1111-4111-8111-111111111111');
        $this->withSecurityPolicy('sign_in.auto_create_users', true);

        $this->completeMicrosoftSignIn('someone@contoso.test', 'a-different-tenant')
            ->assertRedirect(route('sign-in'));

        $this->assertGuest();

        // The check runs BEFORE the account is resolved: doing it after would
        // create a local row for somebody the policy is about to refuse.
        $this->assertNull(User::query()->where('email', 'someone@contoso.test')->first());
    }

    #[Test]
    public function an_address_outside_the_allowed_domains_is_refused(): void
    {
        $this->withSecurityPolicy('sign_in.allowed_email_domains', "contoso.com\nfabrikam.com");
        $this->withSecurityPolicy('sign_in.auto_create_users', true);

        $this->completeMicrosoftSignIn('guest@partner.test')->assertRedirect(route('sign-in'));
        $this->assertGuest();

        // A guest account in the customer tenant carries its HOME domain, which
        // is exactly what this allow-list is for.
        $this->assertNull(User::query()->where('email', 'guest@partner.test')->first());
    }

    #[Test]
    public function an_address_inside_the_allowed_domains_is_admitted(): void
    {
        $this->withSecurityPolicy('sign_in.allowed_email_domains', "contoso.com\nfabrikam.com");
        $this->withSecurityPolicy('sign_in.auto_create_users', true);

        $this->completeMicrosoftSignIn('person@fabrikam.com')->assertRedirect('/');
        $this->assertAuthenticated();
    }

    #[Test]
    public function a_subdomain_is_not_implied_by_its_parent(): void
    {
        // A wildcard nobody asked for is an allow-list that grew on its own.
        $this->withSecurityPolicy('sign_in.allowed_email_domains', 'contoso.com');
        $this->withSecurityPolicy('sign_in.auto_create_users', true);

        $this->completeMicrosoftSignIn('person@mail.contoso.com')->assertRedirect(route('sign-in'));
        $this->assertGuest();
    }

    #[Test]
    public function the_refusal_never_names_the_allowed_tenant_or_domains(): void
    {
        // SEC-DEC-045. SEC-DEC-032 permits telling somebody the state of THEIR
        // OWN account, because Entra has proved who they are. It does not
        // permit handing an outsider the shape of the customer's own policy.
        $this->withSecurityPolicy('sign_in.allowed_tenant_id', '11111111-1111-4111-8111-111111111111');

        $this->completeMicrosoftSignIn('someone@contoso.test', 'a-different-tenant');

        // Asserted against the RENDERED page rather than the error bag, because
        // what matters is what the refused person can actually read.
        $page = $this->get('/sign-in');

        $page->assertOk()
            ->assertSee('This directory account cannot sign in to SemantIQ')
            ->assertSee('Ask an administrator')
            ->assertDontSee('11111111-1111-4111-8111-111111111111')
            ->assertDontSee('a-different-tenant');
    }

    /* ---- The screen ----------------------------------------------------- */

    #[Test]
    public function the_screen_warns_when_entra_is_not_configured(): void
    {
        config()->set('services.microsoft.client_secret', '');

        $admin = $this->localAccount(Role::SystemAdmin, 'local@example.test');

        $this->actingAs($admin)
            ->get(route('admin.security.authentication'))
            ->assertOk()
            ->assertSee('Microsoft Entra is not fully configured');
    }

    #[Test]
    public function the_screen_never_shows_a_credential_value(): void
    {
        // SEC-DEC-017. A screen whose job is "show me what is configured" is
        // the most natural place in an application for a client secret to end
        // up on a page.
        config()->set('services.microsoft.client_secret', 'super-secret-value');

        $admin = $this->localAccount(Role::SystemAdmin, 'local@example.test');

        $this->actingAs($admin)
            ->get(route('admin.security.authentication'))
            ->assertOk()
            ->assertDontSee('super-secret-value');
    }

    #[Test]
    public function the_screen_counts_the_accounts_the_current_mode_shuts_out(): void
    {
        // The number nobody thinks to ask for, and the one that turns a policy
        // change into people unable to sign in on Monday.
        $admin = $this->localAccount(Role::SystemAdmin, 'local@example.test');
        $this->localAccount(Role::Viewer, 'viewer@example.test');

        $this->actingAs($admin)
            ->get(route('admin.security.authentication'))
            ->assertOk()
            ->assertSee('cannot use the sign-in form under the current mode');
    }
}
