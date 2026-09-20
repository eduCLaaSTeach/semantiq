<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\Support\RoleCode;
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
 * Gate C round 3, correction 2. D-159 PROTECTS THE DESTINATION, NOT ONLY THE
 * SECRET.
 *
 * THE DEFECT. The first version of D-159 asked one question: "is the credential
 * itself being replaced or removed?" A credential has a destination, and the
 * destination is the cheaper thing to move:
 *
 *   the SMTP password is already saved
 *     -> somebody with a stolen console session edits the mail server address
 *     -> the password is untouched, so nothing is privileged
 *     -> the change saves immediately
 *     -> the next test or send offers that password to a host they chose.
 *
 * Nothing was stolen and nothing was replaced. The credential was simply handed
 * somewhere new.
 *
 * THE SAME SHAPE EXISTS THREE TIMES: the AI endpoint and deployment, the Fabric
 * directory and application, and SMTP's host, port, security and username. Each
 * is covered below against a family that actually holds a credential, because a
 * case run against an unconfigured family would pass while proving nothing.
 */
final class DestinationChangesRequireStepUpTest extends TestCase
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

    private function givenEmailIsEstablishedAndTested(): void
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

    private function settings(IntegrationFamily $family): array
    {
        $row = IntegrationConfiguration::query()->where('family', $family->value)->first();

        return is_array($row?->settings) ? $row->settings : [];
    }

    /** @return array<string, mixed> */
    private function establish(IntegrationFamily $family, array $fields, string $secretName): array
    {
        app(IntegrationConfigurationWriter::class)->save(
            $family,
            $fields,
            [$secretName => 'the-saved-'.$secretName],
        );

        return $fields;
    }

    /**
     * THE HEADLINE CASE. An established mail password, a moved host, no
     * confirmation asked for.
     *
     * Mutation: remove `host` from IntegrationFamily::destinationFields().
     */
    public function test_moving_the_mail_host_with_a_saved_password_is_refused_without_step_up(): void
    {
        $this->givenEmailIsEstablishedAndTested();

        $response = $this->actingAsAdministrator()
            ->put('/console/integrations/email', ['host' => 'smtp.attacker.example']);

        $this->assertStringContainsString(
            '/console/access/step-up/',
            $response->headers->get('Location') ?? '',
            'THE MAIL DESTINATION MOVED WITH NO RE-AUTHENTICATION. The saved password is now '
            .'pointed at a server nobody confirmed, and the next connection test will offer it.',
        );

        $this->assertSame('smtp.example.test', $this->settings(IntegrationFamily::Email)['host'],
            'The new host was written anyway, so the refusal is cosmetic.');

        // The credential did not move either - it did not have to.
        $this->assertSame(
            self::MAIL_PASSWORD,
            app(IntegrationSecretStore::class)->get('email', 'password'),
        );
    }

    /** Every one of Email's four destination fields, one at a time. */
    public function test_each_email_destination_field_is_privileged_on_its_own(): void
    {
        $this->givenEmailIsEstablishedAndTested();

        /*
         * NO RESET BETWEEN ITERATIONS, AND THAT IS THE POINT. Each attempt is
         * expected to change nothing, so the stored settings must still be the
         * originals at the end of the loop. If one of them DID get written, the
         * next iteration's `$before` would silently absorb it - so `$before` is
         * captured once, outside.
         */
        $before = $this->settings(IntegrationFamily::Email);

        foreach ([
            'host' => 'smtp.attacker.example',
            'port' => '2525',
            'encryption' => 'none',
            'username' => 'someone-else',
        ] as $field => $value) {

            $response = $this->actingAsAdministrator()
                ->put('/console/integrations/email', [$field => $value]);

            $this->assertStringContainsString(
                '/console/access/step-up/',
                $response->headers->get('Location') ?? '',
                "[{$field}] can be changed with no re-authentication while a credential is saved.",
            );

            $this->assertSame($before, $this->settings(IntegrationFamily::Email),
                "[{$field}] was written before the confirmation.");
        }
    }

    /** AI: the endpoint decides which service receives the key. */
    public function test_moving_the_ai_endpoint_with_a_saved_key_is_refused_without_step_up(): void
    {
        $this->establish(
            IntegrationFamily::Ai,
            ['provider' => 'azure_openai', 'endpoint' => 'https://ai.example.test', 'deployment' => 'gpt'],
            'api_key',
        );

        $response = $this->actingAsAdministrator()
            ->put('/console/integrations/ai', ['endpoint' => 'https://ai.attacker.example']);

        $this->assertStringContainsString(
            '/console/access/step-up/',
            $response->headers->get('Location') ?? '',
            'THE AI ENDPOINT MOVED WITH NO RE-AUTHENTICATION. The saved API key is now pointed at '
            .'a service nobody confirmed.',
        );

        $this->assertSame('https://ai.example.test', $this->settings(IntegrationFamily::Ai)['endpoint']);
    }

    /** Fabric: the directory and application decide who the secret authenticates to. */
    public function test_moving_the_fabric_directory_with_a_saved_secret_is_refused_without_step_up(): void
    {
        $this->establish(
            IntegrationFamily::Fabric,
            ['tenant_id' => 'tenant-one', 'client_id' => 'client-one', 'workspace_id' => 'workspace-one'],
            'client_secret',
        );

        foreach (['tenant_id' => 'tenant-two', 'client_id' => 'client-two'] as $field => $value) {
            $response = $this->actingAsAdministrator()
                ->put('/console/integrations/fabric', [$field => $value]);

            $this->assertStringContainsString(
                '/console/access/step-up/',
                $response->headers->get('Location') ?? '',
                "[{$field}] can be changed with no re-authentication while a client secret is saved.",
            );
        }

        $this->assertSame('tenant-one', $this->settings(IntegrationFamily::Fabric)['tenant_id']);
        $this->assertSame('client-one', $this->settings(IntegrationFamily::Fabric)['client_id']);
    }

    /**
     * ...AND A DESTINATION CHANGE IS NOT PRIVILEGED WITH NO CREDENTIAL TO
     * REDIRECT.
     *
     * The other side of the rule, and it has to hold: requiring a Microsoft
     * round trip to type a mail server address on a deployment that has saved
     * nothing would make First-Run and initial setup absurd.
     */
    public function test_a_destination_change_with_no_saved_credential_saves_directly(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Email,
            ['host' => 'smtp.example.test'],
        );

        $response = $this->actingAsAdministrator()
            ->put('/console/integrations/email', ['host' => 'smtp.moved.example']);

        $this->assertStringNotContainsString(
            '/console/access/step-up/',
            $response->headers->get('Location') ?? '',
            'Typing a mail server address on a deployment with no saved credential demanded a '
            .'Microsoft round trip. There is nothing to redirect.',
        );

        $this->assertSame('smtp.moved.example', $this->settings(IntegrationFamily::Email)['host']);
    }

    /**
     * A DISPLAY FIELD IS NOT A DESTINATION.
     *
     * `from_name` cannot cause the saved password to be offered to a different
     * server, so it must not demand a confirmation. This is not a convenience:
     * an administrator sent to Microsoft for editing a sender name is one who
     * stops reading what they are confirming.
     *
     * Mutation: add `from_name` to destinationFields().
     */
    public function test_a_display_field_does_not_demand_re_authentication(): void
    {
        $this->givenEmailIsEstablishedAndTested();

        $response = $this->actingAsAdministrator()
            ->put('/console/integrations/email', ['from_name' => 'SemantIQ Operations']);

        $this->assertStringNotContainsString(
            '/console/access/step-up/',
            $response->headers->get('Location') ?? '',
            'Editing a display-only sender name demanded re-authentication.',
        );

        $this->assertSame('SemantIQ Operations', $this->settings(IntegrationFamily::Email)['from_name']);
    }

    /**
     * RE-SUBMITTING THE SAME VALUES IS NOT A CHANGE.
     *
     * Every save posts the whole form. Classifying on submission rather than on
     * difference would demand a Microsoft round trip for pressing Save with
     * nothing edited.
     *
     * Mutation: classify any submitted destination field as privileged.
     */
    public function test_re_submitting_unchanged_destination_values_is_not_privileged(): void
    {
        $this->givenEmailIsEstablishedAndTested();

        $response = $this->actingAsAdministrator()->put('/console/integrations/email', [
            'host' => 'smtp.example.test',
            // The stored value is the INTEGER 587 and the form posts "587".
            // A strict comparison would call this a change every time.
            'port' => '587',
            'encryption' => 'tls',
            'username' => 'postmaster',
            'from_name' => 'SemantIQ',
        ]);

        $this->assertStringNotContainsString(
            '/console/access/step-up/',
            $response->headers->get('Location') ?? '',
            'Pressing Save with nothing edited demanded re-authentication. A confirmation people '
            .'meet for no reason is one they learn to click through.',
        );
    }

    /** The staged secret is never stored in the clear. */
    public function test_a_staged_reconfiguration_keeps_its_secret_encrypted(): void
    {
        $this->givenEmailIsEstablishedAndTested();

        $this->actingAsAdministrator()->put('/console/integrations/email', [
            'host' => 'smtp.moved.example',
            'secret_password' => 'the-brand-new-password',
        ]);

        $row = DB::table('staged_integration_changes')->latest('id')->first();

        $this->assertNotNull($row);
        $this->assertStringNotContainsString('the-brand-new-password', (string) $row->ciphertext);
        $this->assertStringNotContainsString('the-brand-new-password', json_encode($row, JSON_THROW_ON_ERROR));

        // ...and the destination is staged beside it, in the same row.
        $this->assertStringContainsString('smtp.moved.example', (string) $row->fields);
    }

    /**
     * AN EXPIRED CONFIRMATION APPLIES NOTHING - not the credential, and not
     * the destination.
     */
    public function test_an_expired_confirmation_applies_neither_half(): void
    {
        $this->givenEmailIsEstablishedAndTested();

        $this->actingAsAdministrator()->put('/console/integrations/email', [
            'host' => 'smtp.moved.example',
            'secret_password' => 'the-brand-new-password',
        ]);

        $staged = StagedIntegrationChange::query()->latest('id')->firstOrFail();

        $this->travel(31)->minutes();

        $applied = DB::transaction(
            fn () => app(StagedChangeStore::class)->apply((int) $staged->getKey(), $this->admin->id),
        );

        $this->assertNull($applied, 'An expired staged change was applied.');

        $this->assertSame('smtp.example.test', $this->settings(IntegrationFamily::Email)['host']);
        $this->assertSame(
            self::MAIL_PASSWORD,
            app(IntegrationSecretStore::class)->get('email', 'password'),
        );
    }

    /** The step-up row carries the family and an id, and nothing about the change. */
    public function test_the_step_up_row_carries_no_destination_detail(): void
    {
        $this->givenEmailIsEstablishedAndTested();

        $this->actingAsAdministrator()->put('/console/integrations/email', [
            'host' => 'smtp.moved.example',
            'secret_password' => 'the-brand-new-password',
        ]);

        $pending = PendingStepUp::query()->latest('id')->firstOrFail();

        $encoded = json_encode($pending->getAttributes(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('smtp.moved.example', $encoded,
            'The new destination is in the privileged-action table, which is structural and read '
            .'by every listing of pending confirmations.');
        $this->assertStringNotContainsString('the-brand-new-password', $encoded);
    }
}
