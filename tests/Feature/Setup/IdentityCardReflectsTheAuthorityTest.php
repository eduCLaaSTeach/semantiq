<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\PlatformSetting;
use App\Modules\Platform\Setup\Support\SetupProjection;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EntraTokenFactory;
use Tests\TestCase;

/**
 * A DEFECT FOUND BY DEPLOYING, AND ONLY BY DEPLOYING.
 *
 * The Integrations screen asked "is there a row with every field and a stored
 * secret" for all four integrations. For three of them that is the whole
 * question. For Microsoft Entra ID it is not, because identity has TWO
 * possible authorities and the row is only one of them: until the controlled
 * cutover a deployment reads its Microsoft configuration from the environment,
 * where there is no row at all.
 *
 * So production - the one deployment whose sign-in demonstrably works, because
 * people were signing in through it - would have been told **Microsoft Entra
 * ID: Not configured**, on a REQUIRED integration, on the single screen where
 * acting on that advice locks everybody out.
 *
 * WHY GATE C DID NOT CATCH IT. The browser verification ran against a local
 * server with no Microsoft configuration, so "Not configured" was the correct
 * answer there - for a reason unrelated to the rule being checked. That is the
 * failure CLAUDE.md section 2 names, and it is why these cases all begin by
 * establishing an authority rather than by establishing a row.
 *
 * THE DIRECTION OF THE FIX MATTERS. Nothing here makes the card report a
 * POSITIVE result. Being configured and being known to work are different
 * facts, and test_a_working_deployment_is_not_reported_as_tested pins that
 * they stay different.
 */
final class IdentityCardReflectsTheAuthorityTest extends TestCase
{
    use RefreshDatabase;

    private function identityStatus(): string
    {
        return app(SetupProjection::class)->forFamily(IntegrationFamily::Identity)->status;
    }

    private function saveACompleteCandidateToTheStore(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Identity,
            [
                'tenant_id' => '11111111-2222-3333-4444-555555555555',
                'client_id' => '99999999-8888-7777-6666-555555555555',
                'redirect_uri' => 'https://semantiq.test/auth/microsoft/callback',
            ],
            ['client_secret' => 'the-secret-the-operator-pasted'],
        );
    }

    /**
     * THE PRODUCTION SHAPE, exactly: sign-in works, and it comes from the
     * environment.
     *
     * Mutation: return the row test for Identity as well.
     */
    public function test_an_environment_backed_deployment_is_not_reported_as_unconfigured(): void
    {
        (new EntraTokenFactory)->configure();

        $this->assertSame(
            PlatformSetting::SOURCE_ENV,
            PlatformSetting::current()->identity_source,
            'The premise of this case is a deployment that has NOT cut over.',
        );

        $this->assertTrue(
            app(SetupProjection::class)->identityIsReady(),
            'The premise of this case is a deployment whose Microsoft sign-in is complete.',
        );

        $this->assertNotSame(
            HealthStatus::NotConfigured->value,
            $this->identityStatus(),
            'The Integrations screen told a deployment with working Microsoft sign-in that '
            .'Microsoft sign-in is not configured. A false red on the one screen where acting '
            .'on it locks everybody out.',
        );

        $this->assertSame(
            HealthStatus::NotChecked->value,
            $this->identityStatus(),
            'A complete configuration nobody has tested reads Not checked. Not configured is '
            .'wrong, and anything positive would be worse.',
        );
    }

    /**
     * THE OTHER DIRECTION, which the fix must not break.
     *
     * Mutation: make the Identity branch return true unconditionally.
     */
    public function test_a_fresh_deployment_with_no_authority_is_still_unconfigured(): void
    {
        $this->assertSame(
            HealthStatus::NotConfigured->value,
            $this->identityStatus(),
            'A brand-new installation has neither an environment configuration nor a saved one. '
            .'Not configured is the honest answer and the one First-Run depends on.',
        );
    }

    /**
     * FIRST-RUN. The administrator has just typed the configuration in. The
     * environment is still empty and the cutover has not happened.
     *
     * Mutation: ask resolve() alone.
     */
    public function test_a_saved_candidate_counts_before_the_cutover(): void
    {
        $this->saveACompleteCandidateToTheStore();

        $this->assertSame(
            PlatformSetting::SOURCE_ENV,
            PlatformSetting::current()->identity_source,
            'The premise of this case is that the cutover has not happened yet.',
        );

        $this->assertNotSame(
            HealthStatus::NotConfigured->value,
            $this->identityStatus(),
            'Setup told the administrator that the Microsoft details they had just saved were '
            .'not configured, because the environment they are replacing is still empty.',
        );
    }

    /** After the cutover the store is the authority, and it is complete. */
    public function test_a_store_backed_deployment_is_configured(): void
    {
        $this->saveACompleteCandidateToTheStore();

        $settings = PlatformSetting::forUpdate();
        $settings->identity_source = PlatformSetting::SOURCE_STORE;
        $settings->save();

        app(IdentityConfigurationSource::class)->forget();

        $this->assertNotSame(
            HealthStatus::NotConfigured->value,
            $this->identityStatus(),
        );
    }

    /**
     * CONFIGURED IS NOT WORKING, and the fix must not blur them.
     *
     * Mutation: have the Identity branch report Available.
     */
    public function test_a_working_deployment_is_not_reported_as_tested(): void
    {
        (new EntraTokenFactory)->configure();

        $view = app(SetupProjection::class)->forFamily(IntegrationFamily::Identity);

        $this->assertNotSame(HealthStatus::Available->value, $view->status,
            'Nobody has tested this deployment from this screen. Saying it is available is the '
            .'fake green status the Not configured / Not checked split exists to prevent.');

        $this->assertNull($view->lastTestedAt,
            'A configuration that has never been tested must not carry a test timestamp.');
    }

    /**
     * THE OTHER THREE FAMILIES ARE UNCHANGED. Identity is the exception
     * because identity has two authorities; email, AI and Fabric have one.
     *
     * Mutation: apply the identity branch to every family.
     */
    public function test_the_optional_integrations_are_unaffected(): void
    {
        (new EntraTokenFactory)->configure();

        foreach ([IntegrationFamily::Email, IntegrationFamily::Ai, IntegrationFamily::Fabric] as $family) {
            $this->assertSame(
                HealthStatus::NotConfigured->value,
                app(SetupProjection::class)->forFamily($family)->status,
                "{$family->value} has never been configured and must still say so, whatever "
                .'Microsoft sign-in is doing.',
            );
        }
    }

    /** The First-Run step list reads the same predicate, so it moved too. */
    public function test_the_setup_step_list_agrees(): void
    {
        (new EntraTokenFactory)->configure();

        $steps = collect(app(SetupProjection::class)->steps())
            ->keyBy('family');

        $this->assertTrue(
            $steps[IntegrationFamily::Identity->value]['configured'],
            'The setup step list and the Integrations card must not be able to disagree about '
            .'whether Microsoft sign-in is configured on one deployment.',
        );

        $this->assertFalse($steps[IntegrationFamily::Email->value]['configured']);
    }
}
