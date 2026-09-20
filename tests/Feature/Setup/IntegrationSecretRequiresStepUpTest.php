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
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * D-159. REPLACING OR REMOVING AN ESTABLISHED CREDENTIAL NEEDS A FRESH SIGN-IN.
 *
 * Establishing one for the first time does not, and that distinction is the
 * rule rather than a convenience: there is nothing to take away, the
 * administrator already holds PlatformAdmin, and requiring it would make
 * First-Run unsatisfiable on a deployment that has no Microsoft yet.
 *
 * THE PLAINTEXT MUST NEVER REACH pending_step_ups, THE SESSION, THE LOG OR THE
 * AUDIT TRAIL. That is asserted against the real rows rather than promised.
 */
final class IntegrationSecretRequiresStepUpTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'zQ9-replacement-secret-never-store-me';

    private const OLD_PASSWORD = 'zQ9-original-secret';

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

    private function givenEmailIsEstablished(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Email,
            ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'postmaster'],
            ['password' => self::OLD_PASSWORD],
        );

        app(IntegrationConfigurationWriter::class)->recordTestResult(
            IntegrationFamily::Email,
            HealthStatus::Available,
            'The mail server accepted the connection.',
        );
    }

    /** ESTABLISHING a credential needs no step-up. Otherwise setup is unsatisfiable. */
    public function test_establishing_a_new_credential_needs_no_step_up(): void
    {
        $this->actingAsAdministrator()
            ->put('/console/integrations/email', [
                'host' => 'smtp.example.test',
                'port' => '587',
                'secret_password' => 'a-brand-new-password',
            ])
            ->assertRedirect();

        $this->assertTrue(app(IntegrationSecretStore::class)->has('email', 'password'),
            'Establishing a first credential was blocked, which would make First-Run '
            .'unsatisfiable on a deployment that has no Microsoft yet.');

        $this->assertSame(0, PendingStepUp::query()->count());
    }

    /**
     * REPLACING AN ESTABLISHED CREDENTIAL IS REDIRECTED TO STEP-UP, and the old
     * value is still in place.
     *
     * Mutation: apply the replacement directly in update(). This must fail.
     */
    public function test_replacing_an_established_credential_requires_step_up(): void
    {
        $this->givenEmailIsEstablished();

        $response = $this->actingAsAdministrator()
            ->put('/console/integrations/email', [
                'host' => 'smtp.example.test',
                'secret_password' => self::NEW_PASSWORD,
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/console/access/step-up/', $response->headers->get('Location') ?? '');

        // NOT YET APPLIED. The confirmation has not happened.
        $this->assertSame(
            self::OLD_PASSWORD,
            app(IntegrationSecretStore::class)->get('email', 'password'),
            'THE CREDENTIAL WAS REPLACED WITHOUT A FRESH SIGN-IN.',
        );

        $pending = PendingStepUp::query()->firstOrFail();
        $this->assertSame(StepUpAction::ReplaceIntegrationSecret, $pending->action);
    }

    /**
     * THE PLAINTEXT IS NOWHERE IT SHOULD NOT BE.
     *
     * Not in the step-up row - that is P1-05's privileged-action table, whose
     * columns are structural and safe to read. Not in the session, which on
     * this deployment is a database table. Not in the audit trail.
     */
    public function test_the_plaintext_never_enters_step_up_storage_or_audit(): void
    {
        $this->givenEmailIsEstablished();

        $this->actingAsAdministrator()
            ->put('/console/integrations/email', ['secret_password' => self::NEW_PASSWORD]);

        $pending = json_encode(PendingStepUp::query()->get()->toArray()) ?: '';

        $this->assertStringNotContainsString(self::NEW_PASSWORD, $pending,
            'THE NEW CREDENTIAL IS IN pending_step_ups. Every listing of pending confirmations is '
            .'now one careless select away from rendering one.');

        foreach (AuditEvent::query()->get() as $event) {
            $this->assertStringNotContainsString(
                self::NEW_PASSWORD,
                json_encode($event->toArray()) ?: '',
                'The new credential reached the audit trail.',
            );
        }

        // Staged, and staged ENCRYPTED.
        $staged = StagedIntegrationChange::query()->firstOrFail();
        $this->assertNotNull($staged->ciphertext);
        $this->assertStringNotContainsString(self::NEW_PASSWORD, (string) $staged->ciphertext,
            'The staged payload contains its own plaintext.');
    }

    /** A staged change is not serialisable into a response. */
    public function test_the_staged_row_hides_its_payload(): void
    {
        $this->givenEmailIsEstablished();

        $this->actingAsAdministrator()
            ->put('/console/integrations/email', ['secret_password' => self::NEW_PASSWORD]);

        $staged = StagedIntegrationChange::query()->firstOrFail();

        $this->assertArrayNotHasKey('ciphertext', $staged->toArray(),
            'The staged change serialises its payload, so anything that returns one returns a '
            .'credential.');
    }

    /**
     * A REFUSED OR EXPIRED STEP-UP CHANGES NOTHING.
     *
     * The staged value expires with the confirmation it belongs to, and the
     * stored credential is untouched.
     */
    public function test_an_expired_step_up_leaves_the_configuration_unchanged(): void
    {
        $this->givenEmailIsEstablished();

        $this->actingAsAdministrator()
            ->put('/console/integrations/email', ['secret_password' => self::NEW_PASSWORD]);

        $this->travel(31)->minutes();

        $this->assertSame(
            self::OLD_PASSWORD,
            app(IntegrationSecretStore::class)->get('email', 'password'),
            'The credential changed although the confirmation expired.',
        );

        // And the previous test result is still standing, because nothing
        // meaningful actually happened.
        $row = IntegrationConfiguration::query()->where('family', 'email')->firstOrFail();
        $this->assertSame(HealthStatus::Available->value, $row->status);
    }

    /**
     * THE NON-SECRET FIELDS ARE STAGED, NOT SAVED - Gate C round 3.
     *
     * THIS CASE USED TO ASSERT THE OPPOSITE, and the reasoning was wrong in
     * both halves. It said the fields were "not the privileged part" and that
     * discarding them would mean re-typing, so it saved them before the
     * redirect - which meant a confirmed change could land in two pieces, and
     * left the configuration holding a NEW HOST beside an OLD PASSWORD for as
     * long as the administrator took at Microsoft. That is the exact mixed
     * state a step-up exists to make unreachable.
     *
     * Both concerns are still met: nothing is re-typed, because the whole
     * change is carried in the staged row and applied on return.
     *
     * Mutation: save the fields before staging, as the first version did.
     */
    public function test_the_typed_fields_are_staged_and_not_written_before_confirmation(): void
    {
        $this->givenEmailIsEstablished();

        $this->actingAsAdministrator()
            ->put('/console/integrations/email', [
                'host' => 'smtp.moved.example',
                'secret_password' => self::NEW_PASSWORD,
            ]);

        $row = IntegrationConfiguration::query()->where('family', 'email')->firstOrFail();

        $this->assertSame('smtp.example.test', $row->settings['host'],
            'THE NEW HOST WAS WRITTEN BEFORE ANYBODY CONFIRMED IT. Until the administrator comes '
            .'back from Microsoft the deployment now holds the new destination beside the old '
            .'credential - so the next connection test offers the saved password to a server '
            .'nobody has authorised.');

        // ...and it is not lost either. It is waiting in the staged row.
        $staged = StagedIntegrationChange::query()->latest('id')->firstOrFail();

        $this->assertSame(
            StagedIntegrationChange::OPERATION_RECONFIGURE,
            $staged->operation,
            'A change carrying both a credential and a destination was staged as something other '
            .'than a reconfiguration, so the two halves can be applied separately.',
        );

        $this->assertSame('smtp.moved.example', $staged->fields['host'] ?? null,
            'The typed field was discarded on the way to Microsoft, so the administrator has to '
            .'type it again when they come back.');
    }

    /**
     * ...AND THE WHOLE CHANGE LANDS AT ONCE WHEN IT IS CONFIRMED.
     *
     * The other half of the same property. A staged reconfiguration that
     * applied only its credential, or only its fields, would be the mixed
     * state arriving a few seconds later instead.
     */
    public function test_a_confirmed_reconfiguration_applies_both_halves_together(): void
    {
        $this->givenEmailIsEstablished();

        $this->actingAsAdministrator()
            ->put('/console/integrations/email', [
                'host' => 'smtp.moved.example',
                'secret_password' => self::NEW_PASSWORD,
            ]);

        $staged = StagedIntegrationChange::query()->latest('id')->firstOrFail();

        DB::transaction(function () use ($staged): void {
            app(StagedChangeStore::class)->apply((int) $staged->getKey(), $this->admin->id);
        });

        $row = IntegrationConfiguration::query()->where('family', 'email')->firstOrFail();

        $this->assertSame('smtp.moved.example', $row->settings['host']);
        $this->assertSame(self::NEW_PASSWORD, app(IntegrationSecretStore::class)->get('email', 'password'));

        // The stored result went with them: it described neither.
        $this->assertSame(HealthStatus::NotChecked->value, $row->status);
        $this->assertNull($row->last_tested_at);
    }

    /** A plain Test connection requires no step-up. It changes nothing. */
    public function test_a_connection_test_requires_no_step_up(): void
    {
        $this->givenEmailIsEstablished();

        $this->actingAsAdministrator()
            ->post('/console/integrations/email/test')
            ->assertRedirect();

        $this->assertSame(0, PendingStepUp::query()->count(),
            'A connection test began a step-up. It changes nothing and must not require one.');
    }
}
