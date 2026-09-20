<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\Identity\IdentityCutover;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Models\PlatformSetting;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * C4 and C5. CORRECTION 4: A FRESH INSTALLATION REACHES THE STORE WITHOUT AN
 * SSH COMMAND.
 *
 * The design had one cutover path, and it begins at .env. A genuinely fresh
 * deployment has no .env identity configuration to begin from, so First-Run
 * would have been completable and the installation still unusable: the
 * nominated administrator opens /auth/microsoft, which reads an empty .env,
 * and cannot sign in. The only escape would be the SSH command First-Run
 * exists to avoid.
 *
 * EVERY CASE HERE CONFIGURES NO IDENTITY .env AT ALL. That is deliberate and
 * load-bearing. A case that left the test harness's identity configuration in
 * place could pass by accidentally reading the environment - which is exactly
 * the behaviour being ruled out - and would keep passing with the fix removed.
 */
final class FreshInstallationReachesTheStoreTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT = '11111111-2222-3333-4444-555555555555';

    protected function setUp(): void
    {
        parent::setUp();

        // A GENUINELY FRESH DEPLOYMENT. Nothing in the environment.
        config([
            'identity.microsoft.tenant_id' => '',
            'identity.microsoft.client_id' => '',
            'identity.microsoft.client_secret' => '',
            'identity.microsoft.redirect_uri' => '',
        ]);
    }

    private function microsoftAnswers(): void
    {
        Http::fake([
            '*/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://login.microsoftonline.com/'.self::TENANT.'/v2.0',
                'authorization_endpoint' => 'https://login.microsoftonline.com/'.self::TENANT.'/oauth2/v2.0/authorize',
                'token_endpoint' => 'https://login.microsoftonline.com/'.self::TENANT.'/oauth2/v2.0/token',
                'jwks_uri' => 'https://login.microsoftonline.com/'.self::TENANT.'/discovery/v2.0/keys',
            ]),
            '*/discovery/v2.0/keys' => Http::response([
                'keys' => [['kty' => 'RSA', 'kid' => 'a-key', 'n' => 'abc', 'e' => 'AQAB']],
            ]),
        ]);
    }

    private function typeTheConfigurationIntoFirstRun(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Identity,
            [
                'tenant_id' => self::TENANT,
                'client_id' => '99999999-8888-7777-6666-555555555555',
                'redirect_uri' => route('auth.microsoft.callback'),
            ],
            ['client_secret' => 'the-secret-the-operator-pasted'],
        );
    }

    /** The premise: with an empty .env and no cutover, nothing is configured. */
    public function test_the_premise_a_fresh_deployment_has_no_identity_configuration(): void
    {
        $identity = app(IdentityConfigurationSource::class)->resolve();

        $this->assertSame(PlatformSetting::SOURCE_ENV, $identity->source);
        $this->assertFalse($identity->isComplete());
        $this->assertSame(
            ['MICROSOFT_TENANT_ID', 'MICROSOFT_CLIENT_ID', 'MICROSOFT_CLIENT_SECRET', 'MICROSOFT_REDIRECT_URI'],
            $identity->missingKeys(),
        );
    }

    /**
     * ...and this is the defect itself: saving alone does NOT make sign-in
     * work. If it did, Correction 4 would be unnecessary and C4 below would
     * pass for the wrong reason.
     */
    public function test_the_defect_saving_the_configuration_alone_does_not_move_the_authority(): void
    {
        $this->typeTheConfigurationIntoFirstRun();

        $identity = app(IdentityConfigurationSource::class)->resolve();

        $this->assertSame(PlatformSetting::SOURCE_ENV, $identity->source);
        $this->assertSame('', $identity->tenantId,
            'Saving the configuration changed what sign-in reads, which would mean a silent '
            .'fallback rather than an explicit transition.');
    }

    /**
     * C4. THE CASE. Fresh deployment + EMPTY identity .env + configuration
     * saved and verified through Bootstrap -> normal Microsoft auth resolves
     * the STORED configuration.
     *
     * Mutation: leave identity_source at `env` after a passing verification -
     * i.e. delete the DB::transaction block in
     * IdentityCutover::commitFreshInstallation(). This case must fail, and it
     * is the exact defect.
     */
    public function test_c4_a_verified_fresh_installation_makes_microsoft_auth_read_the_store(): void
    {
        $this->microsoftAnswers();
        $this->typeTheConfigurationIntoFirstRun();

        $result = app(IdentityCutover::class)->commitFreshInstallation();

        $this->assertTrue($result['committed'], 'A verified fresh installation did not commit.');

        $identity = app(IdentityConfigurationSource::class)->resolve();

        $this->assertSame(PlatformSetting::SOURCE_STORE, $identity->source);
        $this->assertSame(self::TENANT, $identity->tenantId);
        $this->assertSame('the-secret-the-operator-pasted', $identity->clientSecret);
        $this->assertTrue($identity->isComplete());

        // THE PROVIDER THE SIGN-IN ROUTE ACTUALLY USES, resolved through the
        // container exactly as /auth/microsoft resolves it. Asserting only on
        // the source would leave the container bindings unproven, and they are
        // the half that had to change.
        $this->assertTrue(app(IdentityProvider::class)->isConfigured(),
            'The identity provider is still unconfigured, so /auth/microsoft cannot start a sign-in '
            .'even though the configuration was entered and verified.');
    }

    /** No SSH command was involved, and none is required. */
    public function test_c4_no_cutover_command_is_required(): void
    {
        $this->microsoftAnswers();
        $this->typeTheConfigurationIntoFirstRun();

        app(IdentityCutover::class)->commitFreshInstallation();

        $this->assertTrue(PlatformSetting::current()->identityReadsStore());
        $this->assertNotNull(PlatformSetting::current()->identity_committed_at);
    }

    /**
     * C5. A FAILING verification does not move the authority.
     *
     * Mutation: move the flag on save rather than on verification. This case
     * must fail - and the deployment it protects is one where nobody can sign
     * in to correct the mistake.
     */
    public function test_c5_a_failing_verification_does_not_move_the_authority(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $this->typeTheConfigurationIntoFirstRun();

        $result = app(IdentityCutover::class)->commitFreshInstallation();

        $this->assertFalse($result['committed']);
        $this->assertSame(HealthStatus::Unavailable, $result['status']);

        $this->assertSame(PlatformSetting::SOURCE_ENV, PlatformSetting::current()->identity_source,
            'A FAILING verification moved the identity authority. The deployment now reads a '
            .'configuration nobody has shown to work, and nobody can sign in to fix it.');

        $this->assertSame(HealthStatus::NotChecked->value,
            IntegrationConfiguration::query()->where('family', 'identity')->firstOrFail()->status,
            'A failed verification recorded a status other than Not checked.');
    }

    /** An incomplete candidate is Not checked, never Unavailable. */
    public function test_c5_an_incomplete_candidate_is_not_checked_rather_than_failed(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Identity,
            ['tenant_id' => self::TENANT],
        );

        $result = app(IdentityCutover::class)->commitFreshInstallation();

        $this->assertFalse($result['committed']);
        $this->assertSame(HealthStatus::NotChecked, $result['status'],
            'A half-entered configuration was reported as FAILED rather than not yet checked, '
            .'which tells an administrator their details are wrong when they are merely absent.');
    }

    /** The probe writes into its own cache namespace, never live trust. */
    public function test_the_probe_cannot_poison_live_trust(): void
    {
        $this->microsoftAnswers();
        $this->typeTheConfigurationIntoFirstRun();

        app(IdentityCutover::class)->verifyCandidate();

        $this->assertNotNull(
            cache()->get('semantiq:entra-probe:'.self::TENANT.':metadata'),
            'The probe did not use its own cache namespace.',
        );

        $this->assertNull(
            cache()->get('semantiq:entra:'.self::TENANT.':metadata'),
            'A CONNECTION TEST WROTE INTO THE CACHE THE SIGN-IN PATH READS. A test that can break '
            .'authentication for everyone is the opposite of what a test is for.',
        );
    }
}
