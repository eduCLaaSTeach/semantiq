<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Platform\Models\BootstrapGrant;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\PlatformSetting;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D-159, THE BOOTSTRAP HALF. THE LOCAL PASSWORD, AGAIN, BEFORE A PRIVILEGED
 * CHANGE.
 *
 * Microsoft step-up cannot be used here: Microsoft may not exist yet, which is
 * the entire reason First-Run exists. A rule that required it would make the
 * setup flow unsatisfiable on exactly the deployment it was built for.
 *
 * THE PASSWORD COMES FROM THE REQUEST BODY AND IS DISCARDED. Never the session,
 * never the log, never Audit - asserted against the real rows.
 */
final class BootstrapReconfirmationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'the-operator-typed-this-once';

    private const TENANT = '11111111-2222-3333-4444-555555555555';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'identity.microsoft.tenant_id' => '',
            'identity.microsoft.client_id' => '',
            'identity.microsoft.client_secret' => '',
            'identity.microsoft.redirect_uri' => '',
        ]);

        BootstrapAdministrator::query()->create([
            'singleton' => BootstrapAdministrator::SINGLETON,
            'email' => 'setup@example.test',
            'password_hash' => Hash::make(self::PASSWORD),
        ]);

        $this->post('/first-run/sign-in', [
            'email' => 'setup@example.test',
            'password' => self::PASSWORD,
        ])->assertRedirect(route('first_run.overview'));
    }

    private function givenEmailSecretIsEstablished(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Email,
            ['host' => 'smtp.example.test', 'port' => 587],
            ['password' => 'the-original-mail-password'],
        );
    }

    private function givenIdentityIsReady(): void
    {
        app(IntegrationConfigurationWriter::class)->save(IntegrationFamily::Identity, [
            'tenant_id' => self::TENANT,
            'client_id' => '99999999-8888-7777-6666-555555555555',
            'redirect_uri' => route('auth.microsoft.callback'),
        ], ['client_secret' => 'the-entra-secret']);

        $settings = PlatformSetting::forUpdate();
        $settings->identity_source = PlatformSetting::SOURCE_STORE;
        $settings->save();

        app(IdentityConfigurationSource::class)->forget();
    }

    /** ESTABLISHING a secret during setup needs no reconfirmation. */
    public function test_establishing_a_secret_needs_no_reconfirmation(): void
    {
        $this->put('/first-run/integration/email', [
            'host' => 'smtp.example.test',
            'secret_password' => 'a-brand-new-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(app(IntegrationSecretStore::class)->has('email', 'password'));
    }

    /**
     * REPLACING AN ESTABLISHED SECRET WITHOUT THE PASSWORD IS REFUSED.
     *
     * Mutation: drop the reconfirmation branch from IntegrationController.
     * This must fail - a bootstrap session left open on an unattended screen
     * would then be enough to replace a credential.
     */
    public function test_replacing_a_secret_without_the_password_is_refused(): void
    {
        $this->givenEmailSecretIsEstablished();

        $this->put('/first-run/integration/email', [
            'secret_password' => 'a-replacement-password',
        ])->assertSessionHasErrors('password');

        $this->assertSame(
            'the-original-mail-password',
            app(IntegrationSecretStore::class)->get('email', 'password'),
            'THE CREDENTIAL WAS REPLACED WITHOUT RECONFIRMATION.',
        );
    }

    /** A WRONG password is refused, and changes nothing. */
    public function test_a_wrong_password_is_refused(): void
    {
        $this->givenEmailSecretIsEstablished();

        $this->put('/first-run/integration/email', [
            'secret_password' => 'a-replacement-password',
            'password' => 'not-the-setup-password',
        ])->assertSessionHasErrors('password');

        $this->assertSame(
            'the-original-mail-password',
            app(IntegrationSecretStore::class)->get('email', 'password'),
        );
    }

    /** The CORRECT password permits the replacement. */
    public function test_the_correct_password_permits_the_replacement(): void
    {
        $this->givenEmailSecretIsEstablished();

        $this->put('/first-run/integration/email', [
            'secret_password' => 'a-replacement-password',
            'password' => self::PASSWORD,
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'a-replacement-password',
            app(IntegrationSecretStore::class)->get('email', 'password'),
        );
    }

    /**
     * THE FIRST-ADMINISTRATOR HANDOFF NEEDS THE PASSWORD.
     *
     * It is the single most privileged act in the product: it decides who runs
     * the deployment.
     *
     * Mutation: drop the reconfirmation from FirstRunController::nominate().
     */
    public function test_the_handoff_without_the_password_is_refused(): void
    {
        $this->givenIdentityIsReady();

        $this->post('/first-run/first-administrator', [
            'email' => 'nominated@example.test',
        ])->assertSessionHasErrors('password');

        $this->assertSame(0, BootstrapGrant::query()->count(),
            'A HANDOVER GRANT WAS ISSUED WITHOUT RECONFIRMATION.');
    }

    /** A wrong password issues no grant either. */
    public function test_the_handoff_with_a_wrong_password_is_refused(): void
    {
        $this->givenIdentityIsReady();

        $this->post('/first-run/first-administrator', [
            'email' => 'nominated@example.test',
            'password' => 'not-the-setup-password',
        ])->assertSessionHasErrors('password');

        $this->assertSame(0, BootstrapGrant::query()->count());
    }

    /** The correct password issues exactly one grant. */
    public function test_the_handoff_with_the_correct_password_issues_one_grant(): void
    {
        $this->givenIdentityIsReady();

        $this->post('/first-run/first-administrator', [
            'email' => 'nominated@example.test',
            'password' => self::PASSWORD,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, BootstrapGrant::query()->count());
        $this->assertSame('nominated@example.test', BootstrapGrant::query()->firstOrFail()->expected_subject);
    }

    /**
     * THE RECONFIRMATION PASSWORD REACHES NO SESSION, NO LOG AND NO AUDIT ROW.
     *
     * Asserted against the real stores. A promise in a comment would be
     * satisfied by a comment.
     */
    public function test_the_password_is_never_persisted_anywhere(): void
    {
        $this->givenEmailSecretIsEstablished();

        $this->put('/first-run/integration/email', [
            'secret_password' => 'a-replacement-password',
            'password' => self::PASSWORD,
        ])->assertSessionHasNoErrors();

        // The session store, which on this deployment is a database table.
        $sessions = json_encode(DB::table('sessions')->get()->toArray()) ?: '';
        $this->assertStringNotContainsString(self::PASSWORD, $sessions,
            'The reconfirmation password is in the session store.');

        foreach (AuditEvent::query()->get() as $event) {
            $this->assertStringNotContainsString(
                self::PASSWORD,
                json_encode($event->toArray()) ?: '',
                'The reconfirmation password reached the audit trail.',
            );
        }

        // And it is not sitting in the bootstrap row either.
        $this->assertStringNotContainsString(
            self::PASSWORD,
            json_encode(BootstrapAdministrator::query()->get()->toArray()) ?: '',
        );
    }

    /**
     * THE REFUSAL IS EXACTLY ONE SENTENCE, whatever went wrong.
     *
     * Asserted as an EQUALITY rather than by hunting for words the message must
     * not contain. A denylist of tells is only ever as good as the list, and
     * the next person to add a helpful detail would add one nobody thought of.
     * Pinning the exact sentence means any added detail fails.
     *
     * The three cases below are genuinely different server-side - a wrong
     * password, a missing password, and a principal that has closed since the
     * page was rendered - and must be indistinguishable from outside.
     */
    public function test_every_reconfirmation_refusal_reads_identically(): void
    {
        $this->givenEmailSecretIsEstablished();

        $expected = 'That password was not accepted.';

        $this->put('/first-run/integration/email', [
            'secret_password' => 'x',
            'password' => 'not-the-setup-password',
        ])->assertSessionHasErrors(['password' => $expected]);

        /*
         * A BLANK password. Different cause server-side - the value never
         * reaches Hash::check at all - and the same sentence.
         *
         * A CLOSED PRINCIPAL IS DELIBERATELY NOT ONE OF THESE CASES. It cannot
         * reach this controller: RequireBootstrapSession re-reads the live
         * state and redirects to sign-in first, so the refusal there is a
         * session refusal rather than a reconfirmation one. The first draft of
         * this case asserted otherwise and was wrong about the product, not
         * about the message.
         */
        $this->put('/first-run/integration/email', [
            'secret_password' => 'x',
            'password' => '',
        ])->assertSessionHasErrors(['password' => $expected]);

        $this->assertSame(
            'the-original-mail-password',
            app(IntegrationSecretStore::class)->get('email', 'password'),
        );
    }

    /**
     * A CLOSED PRINCIPAL REFUSES WITHOUT RAISING, at the guard.
     *
     * The stored hash is deliberately not a recognisable one, so anything that
     * handed it to Hash::check would produce a 500 with a stack trace instead
     * of a refusal - an error page that appears for exactly one reason is a
     * disclosure. Asserted here because it is the case the reconfirmation
     * check above cannot reach.
     */
    public function test_a_closed_principal_refuses_rather_than_raises(): void
    {
        $this->givenEmailSecretIsEstablished();

        $principal = BootstrapAdministrator::current();
        $this->assertNotNull($principal);
        $principal->disabled_at = now();
        $principal->password_hash = BootstrapAdministrator::UNUSABLE_HASH;
        $principal->save();

        $this->put('/first-run/integration/email', [
            'secret_password' => 'x',
            'password' => self::PASSWORD,
        ])->assertRedirect(route('first_run.sign_in'));

        $this->assertSame(
            'the-original-mail-password',
            app(IntegrationSecretStore::class)->get('email', 'password'),
        );
    }
}
