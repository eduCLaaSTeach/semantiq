<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use App\Modules\Platform\Setup\Connections\ConnectionResult;
use App\Modules\Platform\Setup\Connections\SendsTestEmail;
use App\Modules\Platform\Setup\Connections\TestEmailSender;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * D-153. THE TEST MESSAGE GOES TO THE AUTHENTICATED PRINCIPAL AND NOWHERE ELSE.
 *
 * WHY THIS IS THE WHOLE OF THE DECISION. A connection test that could be
 * pointed at an address is an open relay wearing a diagnostic's clothes: it
 * sends mail FROM the customer's own domain, THROUGH their own authenticated
 * server, to anywhere. It is the first thing found by anybody scanning for one,
 * and it would be found on every SemantIQ deployment at once.
 *
 * SO THE GUARANTEE IS STRUCTURAL, NOT VALIDATED. There is no recipient field to
 * validate: the route takes none, the controller consults none, and
 * TestEmailSender::send() has one string parameter whose only caller resolves it
 * from the session. The cases below attack all three of those in turn.
 *
 * THE SENDER ITSELF IS FAKED. Reaching a real SMTP server from a test would be
 * a test of the network, and the Product Owner's instruction is explicit that no
 * real test email is sent from anywhere. What is proven here is which address
 * SemantIQ would send to, and that nothing a caller supplies can change it.
 */
final class TestEmailGoesOnlyToThePrincipalTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'the.administrator@example.test';

    private const BOOTSTRAP_EMAIL = 'setup@example.test';

    private const BOOTSTRAP_PASSWORD = 'the-operator-typed-this-once';

    /** @var list<string> every address send() was asked for, in order */
    private array $sentTo = [];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('integration-test-email:'.self::ADMIN_EMAIL);
        RateLimiter::clear('integration-test-email:'.self::BOOTSTRAP_EMAIL);

        $this->admin = User::query()->create([
            'provider' => 'microsoft',
            'external_subject' => 'oid-admin',
            'tenant_id' => 'tenant-1',
            'email' => self::ADMIN_EMAIL,
            'display_name' => 'The Administrator',
            'status' => UserStatus::Active,
        ]);

        RoleAssignment::query()->create([
            'user_id' => $this->admin->id,
            'organisation_id' => null,
            'role_code' => RoleCode::SystemAdministrator,
            'assigned_at' => now(),
        ]);

        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Email,
            [
                'host' => 'smtp.example.test',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'postmaster',
                'from_address' => 'semantiq@example.test',
                'from_name' => 'SemantIQ',
            ],
            ['password' => 'a-saved-mail-password'],
        );

        /*
         * THE ONE THING FAKED. It records the address it was given and reports
         * success; it opens no socket. What is under test is the resolution of
         * the recipient, not SMTP.
         */
        $this->sentTo = [];

        $this->swap(SendsTestEmail::class, new class($this->sentTo) implements SendsTestEmail
        {
            public function __construct(private array &$log) {}

            public function send(string $recipient): ConnectionResult
            {
                $this->log[] = $recipient;

                return ConnectionResult::available('A test message was sent to your own address.');
            }
        });
    }

    private function actingAsAdministrator(): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $this->admin->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }

    private function givenABootstrapAdministrator(): void
    {
        BootstrapAdministrator::query()->create([
            'singleton' => 'bootstrap',
            'email' => self::BOOTSTRAP_EMAIL,
            'password_hash' => Hash::make(self::BOOTSTRAP_PASSWORD),
        ]);
    }

    private function signInToFirstRun(): self
    {
        $this->post('/first-run/sign-in', [
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => self::BOOTSTRAP_PASSWORD,
        ]);

        return $this;
    }

    // ---------------------------------------------------------------------
    // WHERE THE MESSAGE GOES
    // ---------------------------------------------------------------------

    /**
     * THE HEADLINE. A System Administrator's test goes to their own address.
     *
     * Mutation: read the recipient from the request.
     */
    public function test_the_recipient_is_the_signed_in_administrators_own_address(): void
    {
        $this->actingAsAdministrator()->post('/console/integrations/email/send-test');

        $this->assertSame([self::ADMIN_EMAIL], $this->sentTo,
            'The test message did not go to the signed-in administrator, which is the only address '
            .'D-153 permits.');
    }

    /**
     * During setup it goes to the Bootstrap Administrator's configured address.
     *
     * THE PERMANENT ADMINISTRATOR IS REMOVED FIRST, and that is the product
     * rather than the test being awkward: First-Run exists only while a
     * deployment has NO System Administrator, so a case that left the one from
     * setUp() in place would be signed out at the door and would pass its
     * assertion against an empty log - proving nothing at all.
     */
    public function test_the_bootstrap_recipient_is_the_bootstrap_administrators_address(): void
    {
        RoleAssignment::query()->delete();
        $this->admin->delete();

        $this->givenABootstrapAdministrator();

        $this->signInToFirstRun()
            ->get('/first-run')
            ->assertOk();

        $this->post('/first-run/integration/email/send-test');

        $this->assertSame([self::BOOTSTRAP_EMAIL], $this->sentTo,
            'The setup test message did not go to the Bootstrap Administrator.');
    }

    /**
     * THE REQUEST CANNOT OVERRIDE THE RECIPIENT. Every shape somebody would
     * try.
     *
     * Mutation: `$recipient = $request->input('to') ?? $this->principalEmail(...)`.
     */
    public function test_the_request_cannot_redirect_the_message(): void
    {
        foreach ([
            ['to' => 'attacker@evil.example'],
            ['recipient' => 'attacker@evil.example'],
            ['email' => 'attacker@evil.example'],
            ['address' => 'attacker@evil.example'],
            ['cc' => 'attacker@evil.example'],
            ['bcc' => 'attacker@evil.example'],
            ['to' => [self::ADMIN_EMAIL, 'attacker@evil.example']],
            ['subject' => 'Please reset your password', 'body' => 'Click here'],
        ] as $payload) {
            RateLimiter::clear('integration-test-email:'.self::ADMIN_EMAIL);
            $this->sentTo = [];

            $this->actingAsAdministrator()->post('/console/integrations/email/send-test', $payload);

            $this->assertSame([self::ADMIN_EMAIL], $this->sentTo,
                'A request field changed where the test message went: '.json_encode($payload));
        }
    }

    /**
     * ...AND THE SIGNATURE MAKES IT UNREPRESENTABLE, not merely ignored.
     *
     * A controller that ignores a field today is a controller somebody wires up
     * tomorrow. This asserts the SHAPE: one parameter, a string, named for the
     * recipient - so there is nowhere for a subject, a body or a second address
     * to be passed even by a caller who wanted to.
     */
    public function test_the_sender_has_no_parameter_a_request_could_fill(): void
    {
        $send = (new ReflectionClass(TestEmailSender::class))->getMethod('send');

        $this->assertCount(1, $send->getParameters(),
            'TestEmailSender::send() takes more than a recipient. A subject, a body or a second '
            .'address passed in from outside is the open relay this decision exists to prevent.');

        $parameter = $send->getParameters()[0];
        $type = $parameter->getType();

        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertSame('string', $type->getName());
        $this->assertSame('recipient', $parameter->getName());

        // The message is fixed on the class, not composed from an argument.
        $source = (string) file_get_contents(
            (new ReflectionClass(TestEmailSender::class))->getFileName(),
        );

        $this->assertStringContainsString('public const SUBJECT', $source);
        $this->assertStringContainsString('public const BODY', $source);

        foreach (['->cc(', '->bcc(', '->addTo(', '->addCc('] as $extra) {
            $this->assertStringNotContainsString($extra, $source,
                "TestEmailSender calls [{$extra}], so one message can reach more than one address.");
        }
    }

    // ---------------------------------------------------------------------
    // THE CONFIGURED IDENTITY, AND SAFE FAILURE
    // ---------------------------------------------------------------------

    /**
     * THE CONFIGURED From IS USED, and a missing one is refused rather than
     * guessed.
     *
     * This case drives the REAL sender, not the stand-in - it is about the
     * message it would build, which is the half the stand-in replaces.
     */
    public function test_the_configured_send_from_identity_is_required(): void
    {
        $real = new TestEmailSender(app(IntegrationSecretStore::class));

        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Email,
            ['from_address' => ''],
        );

        $result = $real->send(self::ADMIN_EMAIL);

        $this->assertSame(HealthStatus::NotChecked, $result->status,
            'With no send-from address configured the sender fell back to something else. A test '
            .'that passes for a sender nobody will ever send from proves nothing.');

        $this->assertStringContainsString('send from address', $result->explanation);
    }

    /** ...and the real sender names the configured address, not the username. */
    public function test_the_real_sender_builds_its_from_address_from_the_configuration(): void
    {
        $source = (string) file_get_contents(
            (new ReflectionClass(TestEmailSender::class))->getFileName(),
        );

        $this->assertStringContainsString("\$settings['from_address']", $source);
        $this->assertStringContainsString("\$settings['from_name']", $source);

        $this->assertStringNotContainsString('->from(new Address($username', $source,
            'The test message is sent from the SMTP username rather than the configured send-from '
            .'address, so it proves a permission the deployment does not use.');
    }

    /** An SMTP refusal is reported safely, with nothing of the provider in it. */
    public function test_an_smtp_refusal_is_reported_without_echoing_the_server(): void
    {
        $sender = new class implements SendsTestEmail
        {
            public function send(string $recipient): ConnectionResult
            {
                // What the real class does with a Throwable: choose one of its
                // own sentences. This asserts the controller surfaces it as-is.
                return ConnectionResult::unavailable(
                    'The mail server accepted the connection but refused to send the message.',
                );
            }
        };

        $this->swap(SendsTestEmail::class, $sender);

        $response = $this->actingAsAdministrator()->post('/console/integrations/email/send-test');

        $response->assertSessionHas('confirmation',
            'The mail server accepted the connection but refused to send the message.');

        $source = (string) file_get_contents(
            (new ReflectionClass(TestEmailSender::class))->getFileName(),
        );

        $this->assertStringNotContainsString('$e->getMessage()', str_replace(
            '$message = $e->getMessage();',
            '',
            $source,
        ), 'A provider error message escapes into an explanation.');
    }

    // ---------------------------------------------------------------------
    // RATE LIMIT AND EVIDENCE
    // ---------------------------------------------------------------------

    /**
     * ONE PER ADMINISTRATOR PER MINUTE.
     *
     * Mutation: remove the limiter. Without it the button is a way to send
     * unlimited mail through the customer's server - still to one address, but
     * enough of it to get the deployment's sending reputation ruined.
     */
    public function test_a_second_test_within_the_minute_is_refused(): void
    {
        $this->actingAsAdministrator()->post('/console/integrations/email/send-test');

        $second = $this->actingAsAdministrator()->post('/console/integrations/email/send-test');

        $second->assertSessionHasErrors(['test']);

        $this->assertCount(1, $this->sentTo,
            'A second test message was sent within the minute.');

        $this->travel(61)->seconds();

        $this->actingAsAdministrator()->post('/console/integrations/email/send-test');

        $this->assertCount(2, $this->sentTo, 'The limiter never releases.');
    }

    /** The evidence records the outcome and never the recipient. */
    public function test_the_evidence_carries_no_address(): void
    {
        $this->actingAsAdministrator()->post('/console/integrations/email/send-test');

        $rows = AuditEvent::query()->get()->map(
            fn ($row): string => json_encode($row->getAttributes(), JSON_THROW_ON_ERROR),
        )->implode(' ');

        $this->assertStringNotContainsString(self::ADMIN_EMAIL, $rows,
            'The recipient is in the audit trail. It is a person\'s address, and the outcome is '
            .'what the evidence is for.');

        $this->assertStringNotContainsString('smtp.example.test', $rows);
        $this->assertStringNotContainsString('a-saved-mail-password', $rows);
    }

    /** A principal with no address is told so, and nothing is sent. */
    public function test_a_principal_with_no_address_is_refused(): void
    {
        $this->admin->email = '';
        $this->admin->save();

        $response = $this->actingAsAdministrator()->post('/console/integrations/email/send-test');

        $response->assertSessionHasErrors(['test']);
        $this->assertSame([], $this->sentTo);
    }

    /** There is no send-test route for any family but Email. */
    public function test_no_other_integration_can_send_anything(): void
    {
        foreach (['ai', 'fabric', 'identity'] as $family) {
            $this->actingAsAdministrator()
                ->post("/console/integrations/{$family}/send-test")
                ->assertNotFound();
        }

        $this->assertSame([], $this->sentTo);
    }
}
