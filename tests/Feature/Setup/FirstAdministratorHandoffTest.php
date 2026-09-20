<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Platform\Bootstrap\BootstrapState;
use App\Modules\Platform\Bootstrap\GrantRedeemer;
use App\Modules\Platform\Identity\AuthenticationFailed;
use App\Modules\Platform\Identity\VerifiedIdentity;
use App\Modules\Platform\Models\BootstrapGrant;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Setup\Bootstrap\FirstAdministratorHandoff;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * CORRECTION 2. THE HANDOFF TO THE FIRST PERMANENT SYSTEM ADMINISTRATOR.
 *
 * The first draft said the nomination is an email field and the administrator
 * then signs in normally. CallbackController creates NOBODY without a grant in
 * session, so the nomination screen would have "worked", the nominated person
 * would have been refused as an unknown identity, and the installation would
 * have been left with no administrator and no explanation.
 *
 * These cases assert the handoff goes through the EXISTING GrantIssuer and
 * GrantRedeemer, unchanged, and that every P1-00 property comes with it.
 */
final class FirstAdministratorHandoffTest extends TestCase
{
    use RefreshDatabase;

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
    }

    private function givenIdentityIsStoredAndAuthoritative(): void
    {
        app(IntegrationConfigurationWriter::class)->save(IntegrationFamily::Identity, [
            'tenant_id' => self::TENANT,
            'client_id' => '99999999-8888-7777-6666-555555555555',
            'redirect_uri' => route('auth.microsoft.callback'),
        ], ['client_secret' => 'the-secret']);

        $settings = PlatformSetting::forUpdate();
        $settings->identity_source = PlatformSetting::SOURCE_STORE;
        $settings->save();

        app(IdentityConfigurationSource::class)->forget();
    }

    private function givenALocalAdministrator(): void
    {
        BootstrapAdministrator::query()->create([
            'singleton' => BootstrapAdministrator::SINGLETON,
            'email' => 'setup@example.test',
            'password_hash' => Hash::make('the-password'),
        ]);
    }

    private function identityFor(string $email): VerifiedIdentity
    {
        return new VerifiedIdentity(
            provider: 'microsoft',
            subject: 'oid-'.md5($email),
            tenant: self::TENANT,
            email: $email,
            displayName: 'The Nominated Administrator',
        );
    }

    /** The nomination produces a real, ordinary bootstrap grant. */
    public function test_the_nomination_issues_an_ordinary_bootstrap_grant(): void
    {
        $this->givenIdentityIsStoredAndAuthoritative();

        $link = app(FirstAdministratorHandoff::class)->nominate('Nominated@Example.test');

        $grant = BootstrapGrant::query()->firstOrFail();

        // Every P1-00 property, because it IS P1-00's issuer.
        $this->assertSame('nominated@example.test', $grant->expected_subject);
        $this->assertSame(self::TENANT, $grant->expected_tenant);
        $this->assertNull($grant->consumed_at);
        $this->assertTrue($grant->expires_at->greaterThan(now()));
        $this->assertTrue($grant->expires_at->lessThanOrEqualTo(now()->addMinutes(30)));

        // The link carries the plaintext, which is stored ONLY as a hash.
        $this->assertStringStartsWith(url('/first-run/'), $link);
        $token = substr($link, strrpos($link, '/') + 1);
        $this->assertSame(64, strlen($token));
        $this->assertSame(BootstrapGrant::hashFor($token), $grant->token_hash);
    }

    /**
     * THE TENANT COMES FROM THE VERIFIED STORED CONFIGURATION, NEVER THE FORM.
     *
     * Mutation: take the tenant from user input. A typo then produces a grant
     * that can never be redeemed, discoverable only by the nominated
     * administrator at the moment they try to sign in.
     */
    public function test_the_tenant_is_taken_from_the_stored_configuration(): void
    {
        $this->givenIdentityIsStoredAndAuthoritative();

        app(FirstAdministratorHandoff::class)->nominate('nominated@example.test');

        $this->assertSame(self::TENANT, BootstrapGrant::query()->firstOrFail()->expected_tenant,
            'The grant is bound to a tenant other than the configured one, so it can never be redeemed.');
    }

    /** Nominating before Microsoft is configured refuses, rather than issuing a dead link. */
    public function test_nominating_before_sign_in_is_configured_is_refused(): void
    {
        $this->givenALocalAdministrator();

        $this->expectException(RuntimeException::class);

        app(FirstAdministratorHandoff::class)->nominate('nominated@example.test');
    }

    /**
     * THE WHOLE HANDOFF. The nominated administrator opens the link, signs in,
     * and GrantRedeemer - unchanged - creates the first System Administrator.
     */
    public function test_the_nominated_administrator_becomes_the_first_system_administrator(): void
    {
        $this->givenIdentityIsStoredAndAuthoritative();
        $this->givenALocalAdministrator();

        $link = app(FirstAdministratorHandoff::class)->nominate('nominated@example.test');
        $token = substr($link, strrpos($link, '/') + 1);

        $user = app(GrantRedeemer::class)->redeem($token, $this->identityFor('nominated@example.test'));

        $this->assertTrue($user->exists);
        $this->assertSame('nominated@example.test', $user->email);

        $this->assertTrue(
            app(BootstrapState::class)->isConfigured(),
            'The deployment still has no System Administrator after a successful redemption.',
        );

        // ...and Correction 3 fired in the same transaction.
        $this->assertSame(
            BootstrapAdministrator::UNUSABLE_HASH,
            BootstrapAdministrator::current()?->password_hash,
            'The local bootstrap password survived the handoff.',
        );
    }

    /**
     * A WRONG IDENTITY REFUSES WITHOUT CONSUMING THE GRANT - D-03 rule 7.
     *
     * This is P1-00's property and P1-10 must not weaken it: a grant burned by
     * the wrong person would strand the installation.
     */
    public function test_a_wrong_identity_refuses_without_consuming_the_grant(): void
    {
        $this->givenIdentityIsStoredAndAuthoritative();
        $this->givenALocalAdministrator();

        $link = app(FirstAdministratorHandoff::class)->nominate('nominated@example.test');
        $token = substr($link, strrpos($link, '/') + 1);

        try {
            app(GrantRedeemer::class)->redeem($token, $this->identityFor('somebody.else@example.test'));
            $this->fail('A grant was redeemed by an identity it was not issued for.');
        } catch (AuthenticationFailed) {
            // Expected.
        }

        $this->assertNull(BootstrapGrant::query()->firstOrFail()->consumed_at,
            'THE GRANT WAS CONSUMED BY THE WRONG PERSON. The installation is now stranded: no '
            .'administrator, and the one-time link is spent.');

        $this->assertSame(0, User::query()->count());

        // And the RIGHT person can still use it.
        $user = app(GrantRedeemer::class)->redeem($token, $this->identityFor('nominated@example.test'));
        $this->assertSame('nominated@example.test', $user->email);
    }

    /** The link is never persisted anywhere it could be read back. */
    public function test_the_link_is_not_stored_or_re_displayable(): void
    {
        $this->givenIdentityIsStoredAndAuthoritative();

        $link = app(FirstAdministratorHandoff::class)->nominate('nominated@example.test');
        $token = substr($link, strrpos($link, '/') + 1);

        $row = BootstrapGrant::query()->firstOrFail()->toArray();

        foreach ($row as $column => $value) {
            $this->assertStringNotContainsString($token, (string) $value,
                "The grant plaintext is readable from [{$column}].");
        }

        // The issuer's own evidence carries the subject and tenant, never the token.
        $events = AuditEvent::query()->get();
        $this->assertGreaterThan(0, $events->count());

        foreach ($events as $event) {
            $this->assertStringNotContainsString($token, json_encode($event->toArray()) ?: '',
                'The grant plaintext reached the audit trail.');
        }
    }

    /** No email is sent, and none is required: email is optional in First-Run. */
    public function test_the_handoff_depends_on_no_email_integration(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(FirstAdministratorHandoff::class))->getFileName(),
        );

        foreach (['Mail::', 'Mailable', 'Notification', 'send('] as $needle) {
            $this->assertStringNotContainsString($needle, $source,
                "The handoff names [{$needle}]. Email is OPTIONAL in First-Run, so a mandatory step "
                .'must not depend on it - an installation could reach step 7 and be unable to finish.');
        }
    }
}
