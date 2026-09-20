<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Identity\Configuration\IdentityReconfiguration;
use App\Modules\Identity\StepUp\IdentityReconfigurationCompletion;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\PlatformSetting;
use App\Modules\Platform\Setup\Secrets\StagedIntegrationChange;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gate C round 3, correction 1. MICROSOFT SIGN-IN IS MANAGEABLE AFTER
 * INSTALLATION - and only through P1-02, and only verify-then-activate.
 *
 * THE GAP THIS CLOSES. First-Run could establish an identity configuration;
 * after installation the Entra screen was read-only and said so. A customer
 * whose Entra client secret expired - which they all do - had no route back
 * except SSH, which is the thing this unit exists to remove.
 *
 * WHY EVERY CASE HERE IS ABOUT ROLLBACK. This is the only configuration in
 * SemantIQ whose failure locks everybody out of the deployment that holds it,
 * including the administrator who broke it. So the interesting property is not
 * that a good change works - it is that a bad one changes NOTHING, at every
 * point it could fail: before the confirmation, at the confirmation, and at the
 * verification afterwards.
 */
final class PostInstallSsoChangeTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT = '11111111-2222-3333-4444-555555555555';

    private const NEW_TENANT = '99999999-8888-7777-6666-555555555555';

    private const OLD_SECRET = 'the-old-client-secret';

    private User $admin;

    private bool $microsoftAnswers = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->create([
            'provider' => 'microsoft',
            'external_subject' => 'oid-admin',
            'tenant_id' => self::TENANT,
            'email' => 'admin@example.test',
            'display_name' => 'The Administrator',
            'status' => UserStatus::Active,
        ]);

        RoleAssignment::query()->create([
            'user_id' => $this->admin->id,
            'organisation_id' => null,
            'role_code' => RoleCode::SystemAdministrator,
            'assigned_at' => now(),
        ]);

        $this->givenMicrosoftAnswers();
    }

    /**
     * Microsoft answers correctly for WHICHEVER directory is asked about.
     *
     * DERIVED FROM THE REQUEST URL, not from a captured tenant, and that is not
     * a convenience. `Http::fake()` APPENDS stubs and the FIRST match wins, so
     * calling it a second time with a different tenant leaves the original
     * stub answering - and the probe would receive the OLD directory's issuer
     * for the NEW directory's URL, fail the issuer comparison, and report a
     * perfectly good candidate as broken.
     *
     * The first draft of this file did exactly that and three cases failed for
     * a reason that had nothing to do with the code under test.
     * IdTokenValidationTest records the same trap.
     */
    /**
     * ONE STUB FOR THE WHOLE CLASS, switched by a flag.
     *
     * WHY NOT TWO Http::fake() CALLS. `Http::fake()` APPENDS stubs and the
     * FIRST match wins, so a second call registering a failure leaves the
     * original success stub answering - and a case asserting "the probe failed"
     * passes for reasons that have nothing to do with the code. The first draft
     * did exactly that and two cases reported a successful activation where
     * they expected a refusal.
     *
     * So the stub is registered once and reads a property. Flipping the
     * property is the only way this class changes what Microsoft says, and it
     * cannot be got wrong by ordering.
     *
     * NO RETURN TYPE ON THE CLOSURES, also deliberately: inside a fake,
     * Http::response() returns a PromiseInterface rather than a Response, so a
     * `: Response` hint makes every call throw a TypeError which ProviderProbe
     * catches and reports as "Microsoft did not answer".
     */
    private function givenMicrosoftAnswers(): void
    {
        Http::fake([
            '*/.well-known/openid-configuration' => function ($request) {
                if (! $this->microsoftAnswers) {
                    return Http::response('SECRET-BEARING-PROVIDER-ERROR-BODY', 503);
                }

                // The issuer is derived from the URL, so the stub answers
                // correctly for WHICHEVER directory is asked about - including
                // the new one, which is the whole point of the change path.
                preg_match('#microsoftonline\\.com/([^/]+)/#', (string) $request->url(), $m);
                $tenant = $m[1] ?? 'unknown';

                return Http::response([
                    'issuer' => 'https://login.microsoftonline.com/'.$tenant.'/v2.0',
                    'authorization_endpoint' => 'https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/authorize',
                    'token_endpoint' => 'https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/token',
                    'jwks_uri' => 'https://login.microsoftonline.com/'.$tenant.'/discovery/v2.0/keys',
                ]);
            },
            '*/discovery/v2.0/keys' => function () {
                if (! $this->microsoftAnswers) {
                    return Http::response('SECRET-BEARING-PROVIDER-ERROR-BODY', 503);
                }

                return Http::response([
                    'keys' => [['kty' => 'RSA', 'kid' => 'a-key', 'n' => 'abc', 'e' => 'AQAB']],
                ]);
            },
        ]);
    }

    /** Microsoft does not answer for the directory that was typed. */
    private function givenMicrosoftDoesNotAnswer(): void
    {
        $this->microsoftAnswers = false;
    }

    /** A deployment already signing people in with Microsoft, reading the store. */
    private function givenSsoIsConfigured(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Identity,
            [
                'tenant_id' => self::TENANT,
                'client_id' => 'the-application-id',
                'redirect_uri' => 'http://localhost/auth/microsoft/callback',
            ],
            ['client_secret' => self::OLD_SECRET],
        );

        $settings = PlatformSetting::forUpdate();
        $settings->identity_source = PlatformSetting::SOURCE_STORE;
        $settings->save();

        app(IdentityConfigurationSource::class)->forget();
    }

    private function actingAsAdministrator(): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $this->admin->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }

    private function liveTenant(): string
    {
        app(IdentityConfigurationSource::class)->forget();

        return app(IdentityConfigurationSource::class)->resolve()->tenantId;
    }

    private function liveSecret(): string
    {
        app(IdentityConfigurationSource::class)->forget();

        return app(IdentityConfigurationSource::class)->resolve()->clientSecret;
    }

    // ---------------------------------------------------------------------
    // THE CAPABILITY EXISTS AT ALL
    // ---------------------------------------------------------------------

    /**
     * THE HEADLINE. A post-install administrator can reach a change screen.
     *
     * Mutation: remove the entra.edit route.
     */
    public function test_a_post_install_administrator_can_open_the_change_screen(): void
    {
        $this->givenSsoIsConfigured();

        $response = $this->actingAsAdministrator()->get('/console/identity/entra/change');

        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertSame(self::TENANT, $props['fields']['tenant_id'],
            'The change screen does not pre-fill what is currently in force, so correcting one '
            .'character means re-typing every field.');

        $this->assertTrue($props['secretIsSet']);
    }

    /**
     * ...and the read-only screen links to it and no longer says it is
     * impossible.
     *
     * THIS READS THE COMPONENT SOURCE, not the response. An Inertia response
     * carries the props as JSON and no rendered markup at all, so assertSee on
     * a sentence the React component draws passes or fails for reasons that
     * have nothing to do with the sentence. The first draft asserted on the
     * response and failed against a screen that was correct.
     *
     * The route being reachable is proven behaviourally above; what is checked
     * here is that the stale promise is gone from the page that made it.
     */
    public function test_the_entra_screen_offers_the_change_and_drops_the_stale_wording(): void
    {
        $screen = (string) file_get_contents(base_path('resources/js/Pages/Identity/Entra.jsx'));

        // Comments explain the history and legitimately quote the old wording.
        $body = (string) preg_replace('#/\*.*?\*/#s', '', $screen);

        $this->assertStringContainsString('/console/identity/entra/change', $body,
            'The Microsoft Entra screen does not link to the change screen, so the capability '
            .'exists and nobody can find it.');

        $this->assertStringContainsString('Change configuration', $body);

        foreach ([
            'cannot be changed from this screen',
            'These are set on the server',
            'Set on the server',
        ] as $stale) {
            $this->assertStringNotContainsString($stale, $body,
                "The screen still tells the administrator [{$stale}], which is no longer true.");
        }
    }

    /** THE CLIENT SECRET IS NEVER PRE-FILLED, on a screen that pre-fills the rest. */
    public function test_the_change_screen_never_carries_the_client_secret(): void
    {
        $this->givenSsoIsConfigured();

        $response = $this->actingAsAdministrator()->get('/console/identity/entra/change');

        $this->assertStringNotContainsString(self::OLD_SECRET, $response->getContent(),
            'THE CLIENT SECRET IS IN THE PAGE SOURCE. Everything the read-only screen does to keep '
            .'it out of the props is undone by the screen next to it.');
    }

    // ---------------------------------------------------------------------
    // NOTHING CHANGES WITHOUT A CONFIRMATION
    // ---------------------------------------------------------------------

    /**
     * AN EDIT WITHOUT STEP-UP CHANGES NOTHING.
     *
     * Mutation: apply the fields in EntraController::update() before staging.
     */
    public function test_an_edit_without_step_up_changes_nothing(): void
    {
        $this->givenSsoIsConfigured();

        $response = $this->actingAsAdministrator()
            ->put('/console/identity/entra', ['tenant_id' => self::NEW_TENANT]);

        $this->assertStringContainsString(
            '/console/access/step-up/',
            $response->headers->get('Location') ?? '',
            'Microsoft sign-in was changed with no re-authentication.',
        );

        $this->assertSame(self::TENANT, $this->liveTenant(),
            'THE LIVE DIRECTORY MOVED BEFORE ANYBODY CONFIRMED IT. If the administrator now closes '
            .'the tab, this deployment is pointed at a directory nobody verified and nobody can '
            .'sign in to correct it.');
    }

    /** The staged candidate is encrypted and never reaches the step-up row. */
    public function test_the_candidate_secret_is_staged_encrypted_and_stays_out_of_the_step_up_row(): void
    {
        $this->givenSsoIsConfigured();

        $this->actingAsAdministrator()->put('/console/identity/entra', [
            'client_secret' => 'the-brand-new-client-secret',
        ]);

        $row = DB::table('staged_integration_changes')->latest('id')->first();

        $this->assertNotNull($row);
        $this->assertStringNotContainsString('the-brand-new-client-secret', (string) $row->ciphertext);

        $pending = PendingStepUp::query()->latest('id')->firstOrFail();

        $this->assertSame(StepUpAction::ReconfigureIdentity, $pending->action);
        $this->assertSame(IdentityReconfigurationCompletion::SUBJECT_TYPE, $pending->subject_type);

        $this->assertStringNotContainsString(
            'the-brand-new-client-secret',
            json_encode($pending->getAttributes(), JSON_THROW_ON_ERROR),
            'The candidate secret is in the privileged-action table, which every listing of pending '
            .'confirmations reads.',
        );
    }

    // ---------------------------------------------------------------------
    // VERIFY, THEN ACTIVATE
    // ---------------------------------------------------------------------

    /** A confirmed change whose candidate verifies is applied, atomically. */
    public function test_a_confirmed_and_verified_change_is_activated(): void
    {
        $this->givenSsoIsConfigured();

        $this->actingAsAdministrator()->put('/console/identity/entra', [
            'tenant_id' => self::NEW_TENANT,
            'client_secret' => 'the-brand-new-client-secret',
        ]);

        $staged = StagedIntegrationChange::query()->latest('id')->firstOrFail();

        $result = app(IdentityReconfiguration::class)->activate($staged, $this->admin->id);

        $this->assertTrue($result['activated'], 'A verified candidate was not activated.');
        $this->assertSame(self::NEW_TENANT, $this->liveTenant());
        $this->assertSame('the-brand-new-client-secret', $this->liveSecret());

        // ...and the deployment now reads the store, whatever it read before.
        $this->assertSame(PlatformSetting::SOURCE_STORE, PlatformSetting::current()->identity_source);
    }

    /**
     * A FAILED CANDIDATE PROBE LEAVES THE OLD SSO ACTIVE.
     *
     * This is the case that matters most. The administrator confirmed with
     * Microsoft - so the step-up succeeded - and the details they typed are
     * still wrong. Applying them would be applying a confirmed mistake.
     *
     * Mutation: activate before verifying.
     */
    public function test_a_failed_candidate_probe_leaves_the_old_configuration_active(): void
    {
        $this->givenSsoIsConfigured();

        $this->actingAsAdministrator()->put('/console/identity/entra', [
            'tenant_id' => self::NEW_TENANT,
            'client_secret' => 'the-brand-new-client-secret',
        ]);

        // Microsoft does not answer for the directory that was typed.
        $this->givenMicrosoftDoesNotAnswer();

        $staged = StagedIntegrationChange::query()->latest('id')->firstOrFail();

        $result = app(IdentityReconfiguration::class)->activate($staged, $this->admin->id);

        $this->assertFalse($result['activated']);

        $this->assertSame(self::TENANT, $this->liveTenant(),
            'AN UNVERIFIED DIRECTORY WAS ACTIVATED. Nobody can sign in to this deployment now, '
            .'including whoever just did this.');

        $this->assertSame(self::OLD_SECRET, $this->liveSecret(),
            'The old client secret was replaced by one belonging to a directory that did not '
            .'answer.');
    }

    /** ...and the explanation is the probe's own sentence, carrying nothing provider-shaped. */
    public function test_a_refusal_explains_itself_without_echoing_the_provider(): void
    {
        $this->givenSsoIsConfigured();

        $this->actingAsAdministrator()
            ->put('/console/identity/entra', ['tenant_id' => self::NEW_TENANT]);

        $this->givenMicrosoftDoesNotAnswer();

        $staged = StagedIntegrationChange::query()->latest('id')->firstOrFail();

        $result = app(IdentityReconfiguration::class)->activate($staged, $this->admin->id);

        $this->assertFalse($result['activated']);
        $this->assertStringNotContainsString('SECRET-BEARING', $result['explanation']);
        $this->assertStringNotContainsString('500', $result['explanation']);
        $this->assertNotSame('', $result['explanation'], 'A refusal that says nothing is a dead end.');
    }

    /** An expired confirmation applies nothing. */
    public function test_an_expired_confirmation_applies_nothing(): void
    {
        $this->givenSsoIsConfigured();

        $this->actingAsAdministrator()->put('/console/identity/entra', [
            'tenant_id' => self::NEW_TENANT,
            'client_secret' => 'the-brand-new-client-secret',
        ]);

        $staged = StagedIntegrationChange::query()->latest('id')->firstOrFail();

        $this->travel(31)->minutes();

        $result = app(IdentityReconfiguration::class)->activate($staged, $this->admin->id);

        $this->assertFalse($result['activated']);
        $this->assertSame(self::TENANT, $this->liveTenant());
        $this->assertSame(self::OLD_SECRET, $this->liveSecret());
    }

    /** A staged change cannot be applied twice. */
    public function test_a_confirmation_applies_exactly_once(): void
    {
        $this->givenSsoIsConfigured();

        $this->actingAsAdministrator()
            ->put('/console/identity/entra', ['tenant_id' => self::NEW_TENANT]);

        $staged = StagedIntegrationChange::query()->latest('id')->firstOrFail();

        $first = app(IdentityReconfiguration::class)->activate($staged, $this->admin->id);
        $second = app(IdentityReconfiguration::class)->activate($staged->fresh(), $this->admin->id);

        $this->assertTrue($first['activated']);
        $this->assertFalse($second['activated'], 'A staged identity change was applied twice.');
    }

    // ---------------------------------------------------------------------
    // THE PARTIAL-CLEAR RULE
    // ---------------------------------------------------------------------

    /**
     * A CONFIGURED DEPLOYMENT CANNOT HAVE A REQUIRED FIELD EMPTIED.
     *
     * Replacing is what this screen is for. Emptying half of it is never
     * something anybody meant, and the result is an installation nobody can
     * sign in to.
     *
     * Mutation: drop the partial-clear refusal.
     */
    public function test_a_required_field_cannot_be_cleared_while_sso_is_in_use(): void
    {
        $this->givenSsoIsConfigured();

        foreach (['tenant_id', 'client_id', 'redirect_uri'] as $field) {
            $response = $this->actingAsAdministrator()
                ->put('/console/identity/entra', [$field => '']);

            $response->assertSessionHasErrors([$field]);
        }

        $this->assertSame(0, StagedIntegrationChange::query()->count(),
            'A partial clear was staged, so it is one Microsoft confirmation away from taking out '
            .'the only sign-in path this deployment has.');

        $this->assertSame(self::TENANT, $this->liveTenant());
    }

    /**
     * ...AND THE REFUSAL DOES NOT DEPEND ON ONE FRAMEWORK MIDDLEWARE.
     *
     * Gate C round 2 found a rule that had quietly become
     * ConvertEmptyStringsToNull's responsibility rather than the code's. This
     * drives the same request with that conversion turned off, so a literal
     * empty string reaches the controller and the controller's own check is
     * the only thing that can refuse it.
     *
     * Mutation: check only for `null`, or only for `''`. One of the two cases
     * fails either way.
     */
    public function test_a_literal_empty_string_cannot_clear_a_required_field_either(): void
    {
        $this->givenSsoIsConfigured();

        $response = $this->withoutMiddleware(ConvertEmptyStringsToNull::class)
            ->actingAsAdministrator()
            ->put('/console/identity/entra', ['tenant_id' => '']);

        $response->assertSessionHasErrors(['tenant_id']);

        $this->assertSame(0, StagedIntegrationChange::query()->count());
        $this->assertSame(self::TENANT, $this->liveTenant());
    }

    /** ...but an UNCONFIGURED deployment may still be set up from here. */
    public function test_an_unconfigured_deployment_may_still_be_configured_from_here(): void
    {
        $settings = PlatformSetting::forUpdate();
        $settings->identity_source = PlatformSetting::SOURCE_STORE;
        $settings->save();
        app(IdentityConfigurationSource::class)->forget();

        $response = $this->actingAsAdministrator()->put('/console/identity/entra', [
            'tenant_id' => self::TENANT,
            'client_id' => 'the-application-id',
            'redirect_uri' => 'http://localhost/auth/microsoft/callback',
            'client_secret' => 'a-first-client-secret',
        ]);

        $this->assertStringContainsString(
            '/console/access/step-up/',
            $response->headers->get('Location') ?? '',
            'Setting up sign-in for the first time from the console was refused outright.',
        );
    }

    /** Submitting nothing is refused rather than staged. */
    public function test_an_empty_submission_is_refused(): void
    {
        $this->givenSsoIsConfigured();

        $response = $this->actingAsAdministrator()->put('/console/identity/entra', []);

        $response->assertSessionHasErrors();
        $this->assertSame(0, StagedIntegrationChange::query()->count());
    }

    // ---------------------------------------------------------------------
    // BOUNDARIES THAT MUST NOT MOVE
    // ---------------------------------------------------------------------

    /** P1-09's stored identity health does not survive a new configuration. */
    public function test_the_old_identity_health_does_not_survive_the_change(): void
    {
        $this->givenSsoIsConfigured();

        $before = PlatformSetting::current()->identity_config_revision;

        $this->actingAsAdministrator()
            ->put('/console/identity/entra', ['tenant_id' => self::NEW_TENANT]);

        $staged = StagedIntegrationChange::query()->latest('id')->firstOrFail();
        app(IdentityReconfiguration::class)->activate($staged, $this->admin->id);

        $this->assertGreaterThan(
            $before,
            PlatformSetting::current()->identity_config_revision,
            'THE REVISION DID NOT MOVE, so the previous cached health result is still '
            .'readable and System Health will report the old tenant as the current one.',
        );
    }

    /**
     * PLATFORM INTEGRATIONS STILL HAS NO IDENTITY EDIT FORM.
     *
     * GATE D. The four cards became four tabs, so this asks the same question
     * of every one of them rather than of one page carrying all four. It is a
     * stronger check than it was: identity has to be a summary on its own tab,
     * AND it must not have reappeared as an editable configuration on one of
     * the other three.
     */
    public function test_platform_integrations_still_contains_no_identity_edit_form(): void
    {
        $this->givenSsoIsConfigured();

        $identityProps = $this->actingAsAdministrator()
            ->get('/console/integrations')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertNull(
            $identityProps['integration'],
            'Identity came back as an editable configuration on Platform Integrations, so there '
            .'are two places that write one configuration again.',
        );

        $identity = $identityProps['summary'];

        $this->assertSame('identity', $identity['family']);
        $this->assertArrayNotHasKey('fields', $identity);
        $this->assertArrayNotHasKey('secrets', $identity);

        foreach (['email', 'ai', 'fabric'] as $family) {
            $props = $this->actingAsAdministrator()
                ->get("/console/integrations/{$family}")
                ->assertOk()
                ->viewData('page')['props'];

            $this->assertSame(
                $family,
                $props['integration']['family'],
                "The [{$family}] tab is rendering somebody else's configuration.",
            );
        }
    }

    /** ...and no console integrations route accepts identity, still. */
    public function test_no_console_integrations_route_writes_identity(): void
    {
        $this->givenSsoIsConfigured();

        $this->actingAsAdministrator()
            ->put('/console/integrations/identity', ['tenant_id' => self::NEW_TENANT])
            ->assertNotFound();

        $this->assertSame(self::TENANT, $this->liveTenant());
    }
}
