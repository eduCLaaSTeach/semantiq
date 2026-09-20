<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use App\Modules\Platform\Setup\Secrets\StagedChangeStore;
use App\Modules\Platform\Setup\Secrets\StagedIntegrationChange;
use App\Modules\Platform\Setup\Support\SetupProjection;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gate C correction 4. TWO RELATED DEFECTS.
 *
 * A. NOT CONFIGURED IS NOT NOT CHECKED. "Not checked" reads as "somebody should
 *    press Test", which for an integration nobody has set up sends an
 *    administrator looking for a button to explain a state that has nothing to
 *    do with testing.
 *
 * B. A STORED SECRET COULD BE REPLACED BUT NEVER REMOVED. forget() existed and
 *    no application path reached it, and the form treats a blank field as
 *    "keep". So an optional integration could never be returned to a genuine
 *    unconfigured state.
 */
final class NotConfiguredAndRemovalTest extends TestCase
{
    use RefreshDatabase;

    private const MAIL_PASSWORD = 'zQ8-the-saved-mail-password';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->create([
            'provider' => 'microsoft',
            'external_subject' => 'oid-admin',
            'tenant_id' => 'tenant-1',
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
    }

    private function actingAsAdministrator(): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $this->admin->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }

    private function projectedStatus(IntegrationFamily $family): string
    {
        return app(SetupProjection::class)->forFamily($family)->status;
    }

    private function givenEmailIsCompleteAndTested(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Email,
            ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'postmaster'],
            ['password' => self::MAIL_PASSWORD],
        );

        app(IntegrationConfigurationWriter::class)->recordTestResult(
            IntegrationFamily::Email,
            HealthStatus::Available,
            'The mail server accepted the connection.',
        );
    }

    /**
     * A. AN UNTOUCHED INTEGRATION IS NOT CONFIGURED, in every family.
     *
     * Mutation: default an absent row to NotChecked, as the first draft did.
     */
    public function test_an_untouched_integration_reads_not_configured(): void
    {
        foreach (IntegrationFamily::cases() as $family) {
            $this->assertSame(
                HealthStatus::NotConfigured->value,
                $this->projectedStatus($family),
                "[{$family->value}] reads as Not checked when nothing has been entered, which "
                .'sends an administrator looking for a Test button to explain it.',
            );
        }
    }

    /** A. A COMPLETE but untested configuration is Not checked. That is the distinction. */
    public function test_a_complete_untested_configuration_reads_not_checked(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Email,
            ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'postmaster'],
            ['password' => self::MAIL_PASSWORD],
        );

        $this->assertSame(HealthStatus::NotChecked->value, $this->projectedStatus(IntegrationFamily::Email),
            'A complete configuration nobody has tested reads as Not configured, which hides the '
            .'fact that there IS something to test.');
    }

    /** A. A HALF-entered configuration is still Not configured. */
    public function test_a_partially_entered_configuration_reads_not_configured(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Email,
            ['host' => 'smtp.example.test'],
        );

        $this->assertSame(HealthStatus::NotConfigured->value, $this->projectedStatus(IntegrationFamily::Email));
    }

    /** A. A tested configuration keeps its real result. */
    public function test_a_tested_configuration_keeps_its_result(): void
    {
        $this->givenEmailIsCompleteAndTested();

        $this->assertSame(HealthStatus::Available->value, $this->projectedStatus(IntegrationFamily::Email),
            'A real test result was overwritten by a derived state.');
    }

    /**
     * A. REMOVING A REQUIRED FIELD RETURNS IT TO NOT CONFIGURED.
     *
     * Not merely to Not checked - the configuration is now incomplete, and
     * there is nothing to test.
     */
    public function test_removing_a_required_field_returns_it_to_not_configured(): void
    {
        $this->givenEmailIsCompleteAndTested();

        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Email,
            ['host' => ''],
        );

        $this->assertSame(HealthStatus::NotConfigured->value, $this->projectedStatus(IntegrationFamily::Email));
    }

    /**
     * B. A BLANK SECRET FIELD IS A NO-OP - not a removal, and not a change.
     *
     * THE FIRST VERSION OF THIS CASE WAS SATISFIED BY A REFUSAL, which is the
     * exact failure CLAUDE.md warns about. It asserted only that the credential
     * was still present afterwards. Mutating the controller to treat a blank
     * field as a submitted secret left the credential present too - because the
     * blank then counted as a REPLACEMENT, the request was redirected to
     * Microsoft, and nothing was written yet. The mutation SURVIVED while the
     * screen had quietly become one where changing a port sends you to
     * re-authenticate and then blanks your password.
     *
     * So the claim is now the whole behaviour: the save goes through, the
     * field is saved, no confirmation is demanded, nothing is staged, and the
     * credential is untouched.
     *
     * Mutation: `if (is_string($submitted))` without the `!== ''`.
     */
    public function test_a_blank_secret_field_is_a_no_op(): void
    {
        $this->givenEmailIsCompleteAndTested();

        $response = $this->actingAsAdministrator()
            ->put('/console/integrations/email', ['host' => 'smtp.moved.example', 'secret_password' => '']);

        $location = $response->headers->get('Location') ?? '';

        $this->assertStringNotContainsString('/console/access/step-up/', $location,
            'A BLANK PASSWORD FIELD DEMANDED RE-AUTHENTICATION. Opening this page to change a port '
            .'must not send an administrator to Microsoft, and on return it would have replaced '
            .'their credential with an empty string.');

        $this->assertSame(0, StagedIntegrationChange::query()->count(),
            'A BLANK PASSWORD FIELD STAGED A CREDENTIAL CHANGE. Nothing was asked for, so nothing '
            .'should be waiting to be applied.');

        $this->assertSame(
            self::MAIL_PASSWORD,
            app(IntegrationSecretStore::class)->get('email', 'password'),
            'A BLANK PASSWORD FIELD DELETED THE CREDENTIAL. Anybody who opened this page to change '
            .'a port has just destroyed a working integration.',
        );

        // ...and the edit the administrator actually made DID happen.
        $this->assertSame(
            'smtp.moved.example',
            app(SetupProjection::class)->forFamily(IntegrationFamily::Email)->fields['host'],
            'The non-secret field was not saved, so the blank secret turned the whole save into a '
            .'no-op rather than just itself.',
        );
    }

    /**
     * B. ...AND THE NO-OP DOES NOT DEPEND ON ONE FRAMEWORK MIDDLEWARE.
     *
     * WHY THIS CASE EXISTS. Mutating the controller's `$submitted !== ''` away
     * changed nothing, twice. Not because the test was weak the second time -
     * because Laravel's ConvertEmptyStringsToNull had already turned the blank
     * field into `null` before the controller ran, so `is_string()` rejected it
     * on its own and the mutant was EQUIVALENT.
     *
     * That is worth knowing rather than glossing: the rule an administrator
     * relies on - "leave it blank to keep it" - was being enforced by a global
     * framework middleware, and the explicit check in the controller had never
     * actually been the thing doing it. Remove or reorder that middleware, for
     * any unrelated reason, and a blank password box would start staging a
     * replacement with an empty string.
     *
     * So this drives the same request with the conversion turned OFF, which is
     * the only way to reach the controller with a literal empty string and the
     * only way the explicit check is observable at all.
     *
     * Mutation: `if (is_string($submitted))` without the `!== ''`. This case
     * fails; the one above cannot, and now says so.
     */
    public function test_a_literal_empty_string_is_still_a_no_op(): void
    {
        $this->givenEmailIsCompleteAndTested();

        $response = $this->withoutMiddleware(ConvertEmptyStringsToNull::class)
            ->actingAsAdministrator()
            ->put('/console/integrations/email', ['host' => 'smtp.moved.example', 'secret_password' => '']);

        $this->assertStringNotContainsString(
            '/console/access/step-up/',
            $response->headers->get('Location') ?? '',
            'With the framework\'s empty-string conversion out of the way, a blank password field '
            .'demands re-authentication - so the controller\'s own check is not enforcing the rule '
            .'this screen promises.',
        );

        $this->assertSame(0, StagedIntegrationChange::query()->count(),
            'A literal empty string was staged as a credential replacement.');

        $this->assertSame(
            self::MAIL_PASSWORD,
            app(IntegrationSecretStore::class)->get('email', 'password'),
        );
    }

    /**
     * A. AN INCOMPLETE CONFIGURATION NEVER REPORTS A POSITIVE RESULT.
     *
     * THIS CASE EXISTS BECAUSE A MUTATION SURVIVED. The projection originally
     * derived Not configured only when the stored status was already
     * NotChecked; removing that condition changed no test, because the writer
     * invalidates the status on every write, so the two states cannot normally
     * co-exist.
     *
     * NORMALLY. This writes the impossible pair directly - the state a restored
     * backup, a manual edit or a future write path that forgets to invalidate
     * would produce - and asserts the screen does not repeat a stale success
     * beside a configuration that is missing a required field.
     *
     * Mutation: restore the `$stored === HealthStatus::NotChecked &&` guard.
     */
    public function test_a_stale_positive_result_does_not_survive_an_incomplete_configuration(): void
    {
        $this->givenEmailIsCompleteAndTested();

        // Remove the credential WITHOUT going through the writer, so the stored
        // Available survives. This is the state the invalidation normally
        // prevents, and the point is what happens if it ever does not.
        app(IntegrationSecretStore::class)->forget('email', 'password');

        $this->assertSame(
            HealthStatus::Available->value,
            (string) IntegrationConfiguration::query()->where('family', 'email')->value('status'),
            'The fixture did not reproduce the state this case is about.',
        );

        $this->assertSame(HealthStatus::NotConfigured->value, $this->projectedStatus(IntegrationFamily::Email),
            'A configuration missing its credential still reads as Available. That is the most '
            .'misleading thing this screen can say: it reports a working integration that cannot '
            .'possibly work.');
    }

    /**
     * A. NOT APPLICABLE IS THE ONE STORED STATUS THE DERIVATION MUST NOT TOUCH.
     *
     * It is a product statement - this check cannot apply to this deployment -
     * rather than a report about the configuration. Overriding it with
     * Not configured would send an administrator to fill in something that is
     * deliberately absent.
     *
     * Mutation: drop the NotApplicable arm from the match.
     */
    public function test_not_applicable_is_never_rewritten_to_not_configured(): void
    {
        app(IntegrationConfigurationWriter::class)->recordTestResult(
            IntegrationFamily::Fabric,
            HealthStatus::NotApplicable,
            'This deployment does not use Microsoft Fabric.',
        );

        $this->assertSame(HealthStatus::NotApplicable->value, $this->projectedStatus(IntegrationFamily::Fabric),
            'A deliberate Not applicable was rewritten to Not configured, which asks somebody to '
            .'configure a thing the product has said does not apply.');
    }

    /**
     * B. REMOVAL IS AN EXPLICIT ACTION, AND IT REQUIRES RE-AUTHENTICATION.
     *
     * Mutation: let removeSecret() delete without step-up.
     */
    public function test_removal_requires_re_authentication(): void
    {
        $this->givenEmailIsCompleteAndTested();

        $response = $this->actingAsAdministrator()
            ->delete('/console/integrations/email/secret/password');

        $response->assertRedirect();
        $this->assertStringContainsString('/console/access/step-up/', $response->headers->get('Location') ?? '');

        $this->assertTrue(app(IntegrationSecretStore::class)->has('email', 'password'),
            'THE CREDENTIAL WAS REMOVED WITHOUT A FRESH SIGN-IN.');

        $this->assertSame(
            StepUpAction::RemoveIntegrationSecret,
            PendingStepUp::query()->firstOrFail()->action,
        );
    }

    /**
     * B. THE CONFIRMED REMOVAL DELETES THE CIPHERTEXT AND CLEARS THE EVIDENCE.
     *
     * Applied through the same path the step-up completion uses, inside a
     * transaction, so this exercises the real code rather than a shortcut.
     */
    public function test_a_confirmed_removal_deletes_the_row_and_clears_the_evidence(): void
    {
        $this->givenEmailIsCompleteAndTested();

        $stagedId = app(StagedChangeStore::class)
            ->stageRemoval(IntegrationFamily::Email, 'password', $this->admin->id);

        DB::transaction(function () use ($stagedId): void {
            $applied = app(StagedChangeStore::class)->apply($stagedId, $this->admin->id);
            $this->assertNotNull($applied);

            app(IntegrationConfigurationWriter::class)
                ->recordSecretChanged(IntegrationFamily::Email, $this->admin->id);
        });

        $this->assertFalse(app(IntegrationSecretStore::class)->has('email', 'password'),
            'The credential still reports as configured after removal.');

        $this->assertSame(0, DB::table('integration_secrets')->where('family', 'email')->count(),
            'The ciphertext row survived the removal.');

        $view = app(SetupProjection::class)->forFamily(IntegrationFamily::Email);

        $this->assertFalse($view->secrets['password'],
            'Secret presence still reports true after removal.');

        $this->assertSame(HealthStatus::NotConfigured->value, $view->status,
            'The integration did not return to Not configured after its required credential was '
            .'removed.');

        $this->assertNull($view->lastTestedAt,
            'The old test timestamp survived the removal, so the card still presents evidence for '
            .'a credential that no longer exists.');
    }

    /** B. Removing something that is not saved changes nothing and says so. */
    public function test_removing_an_absent_credential_is_a_no_op(): void
    {
        $this->actingAsAdministrator()
            ->delete('/console/integrations/email/secret/password')
            ->assertRedirect();

        $this->assertSame(0, PendingStepUp::query()->count(),
            'A step-up was begun for a credential that does not exist.');
    }

    /** B. An unknown secret name is refused, not silently ignored. */
    public function test_an_unknown_secret_name_is_refused(): void
    {
        $this->actingAsAdministrator()
            ->delete('/console/integrations/email/secret/api_key')
            ->assertSessionHasErrors('secret');
    }

    /** B. Identity removal is NOT reachable through Platform Integrations. */
    public function test_identity_removal_is_not_reachable_here(): void
    {
        $this->actingAsAdministrator()
            ->delete('/console/integrations/identity/secret/client_secret')
            ->assertNotFound();
    }

    /** B. Nothing about a removal reaches the audit trail as a credential. */
    public function test_a_removal_leaks_nothing(): void
    {
        $this->givenEmailIsCompleteAndTested();

        $stagedId = app(StagedChangeStore::class)
            ->stageRemoval(IntegrationFamily::Email, 'password', $this->admin->id);

        DB::transaction(function () use ($stagedId): void {
            app(StagedChangeStore::class)->apply($stagedId, $this->admin->id);
            app(IntegrationConfigurationWriter::class)
                ->recordSecretChanged(IntegrationFamily::Email, $this->admin->id);
        });

        foreach (AuditEvent::query()->get() as $event) {
            $serialised = json_encode($event->toArray()) ?: '';

            foreach ([self::MAIL_PASSWORD, 'smtp.example.test', 'postmaster'] as $leak) {
                $this->assertStringNotContainsString($leak, $serialised,
                    "The audit trail carries [{$leak}].");
            }
        }
    }
}
