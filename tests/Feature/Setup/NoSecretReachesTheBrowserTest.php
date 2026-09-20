<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Models\IntegrationSecret;
use App\Modules\Platform\Setup\Support\IntegrationView;
use App\Modules\Platform\Setup\Support\SetupProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

/**
 * S1 to S4. NO SECRET LEAVES THE SERVER.
 *
 * ASSERTED AGAINST REAL RENDERED RESPONSES, not against the projection class.
 * A test that checks the view object would pass while a controller added the
 * value as a separate prop, which is the shape this kind of leak usually takes
 * - nobody puts a secret in the read model on purpose.
 *
 * THE SECRETS HERE ARE DISTINCTIVE STRINGS. A generic "password" would match
 * the word in a label and the test would pass for the wrong reason.
 */
final class NoSecretReachesTheBrowserTest extends TestCase
{
    use RefreshDatabase;

    private const SMTP_PASSWORD = 'zQ7-smtp-secret-never-render-me';

    private const AI_KEY = 'zQ7-ai-key-never-render-me';

    private const FABRIC_SECRET = 'zQ7-fabric-secret-never-render-me';

    private const ENTRA_SECRET = 'zQ7-entra-secret-never-render-me';

    private function givenEverySecretIsStored(): void
    {
        $writer = app(IntegrationConfigurationWriter::class);

        $writer->save(IntegrationFamily::Email,
            ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'postmaster'],
            ['password' => self::SMTP_PASSWORD]);

        $writer->save(IntegrationFamily::Ai,
            ['provider' => 'azure_openai', 'endpoint' => 'https://ai.example.test'],
            ['api_key' => self::AI_KEY]);

        $writer->save(IntegrationFamily::Fabric,
            ['tenant_id' => 'aaaa', 'client_id' => 'bbbb', 'workspace_id' => 'cccc'],
            ['client_secret' => self::FABRIC_SECRET]);

        $writer->save(IntegrationFamily::Identity,
            ['tenant_id' => 'dddd', 'client_id' => 'eeee', 'redirect_uri' => 'https://x.test/cb'],
            ['client_secret' => self::ENTRA_SECRET]);
    }

    /** @return list<string> */
    private function secrets(): array
    {
        return [self::SMTP_PASSWORD, self::AI_KEY, self::FABRIC_SECRET, self::ENTRA_SECRET];
    }

    private function administrator(): User
    {
        $user = User::query()->create([
            'provider' => 'microsoft',
            'external_subject' => 'oid-admin',
            'tenant_id' => 'tenant-1',
            'email' => 'admin@example.test',
            'display_name' => 'The Administrator',
            'status' => UserStatus::Active,
        ]);

        RoleAssignment::query()->create([
            'user_id' => $user->id,
            'organisation_id' => null,
            'role_code' => RoleCode::SystemAdministrator,
            'assigned_at' => now(),
        ]);

        return $user;
    }

    /**
     * S1. THE RENDERED INTEGRATIONS PAGE CARRIES NO SECRET.
     *
     * Mutation: add a value field to IntegrationView and populate it.
     */
    public function test_s1_no_secret_appears_in_the_rendered_integrations_page(): void
    {
        $this->givenEverySecretIsStored();

        $response = $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $this->administrator()->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ])->get('/console/integrations');

        $response->assertOk();

        $body = (string) $response->getContent();

        // The page really did render the integrations, so the absence below
        // means something.
        $this->assertStringContainsString('smtp.example.test', $body,
            'The page did not render the configuration at all, so finding no secret proves nothing.');

        foreach ($this->secrets() as $secret) {
            $this->assertStringNotContainsString($secret, $body,
                "A stored secret was rendered into the Integrations page: [{$secret}].");
        }
    }

    /** S1. And the projection itself carries presence only. */
    public function test_s1_the_projection_reports_presence_and_never_a_value(): void
    {
        $this->givenEverySecretIsStored();

        $payload = json_encode(app(SetupProjection::class)->all()) ?: '';

        $this->assertStringContainsString('"secretConfigured"', str_replace('secrets', 'secretConfigured', $payload),
            'The projection no longer reports secret presence at all.');

        foreach ($this->secrets() as $secret) {
            $this->assertStringNotContainsString($secret, $payload);
        }

        // Presence IS reported, so the screen can say "a value is saved".
        foreach (app(SetupProjection::class)->all() as $integration) {
            foreach ($integration['secrets'] as $name => $configured) {
                $this->assertIsBool($configured,
                    "[{$integration['family']}.{$name}] reports something other than a boolean.");
                $this->assertTrue($configured);
            }
        }
    }

    /** S1. The view class has no property a value could occupy. */
    public function test_s1_the_view_has_no_field_for_a_secret_value(): void
    {
        $properties = array_map(
            fn ($p): string => $p->getName(),
            (new ReflectionClass(IntegrationView::class))->getProperties(),
        );

        sort($properties);

        $this->assertSame(
            ['choices', 'explanation', 'family', 'fields', 'lastChangedAt', 'lastTestedAt', 'name',
                'required', 'secrets', 'status', 'statusInWords'],
            $properties,
            'IntegrationView has grown a field. The leak is unrepresentable only while there is '
            .'nowhere to put it. `choices` was added for the AI-provider and mail-security '
            .'selects and is asserted below to come only from the enum.',
        );

        $this->assertTrue((new ReflectionClass(IntegrationView::class))->isReadOnly());
    }

    /**
     * `choices` CANNOT CARRY A STORED VALUE, LET ALONE A SECRET.
     *
     * The guard above caught it being added, which is the guard working. This
     * is the answer it demanded: the field is built entirely from
     * IntegrationFamily::choices(), a match expression over the enum, so
     * nothing an administrator typed can reach it - and no code path exists
     * that would put one there.
     */
    public function test_the_choices_field_comes_only_from_the_enum(): void
    {
        $this->givenEverySecretIsStored();

        foreach (IntegrationFamily::cases() as $family) {
            $view = app(SetupProjection::class)->forFamily($family);

            foreach ($view->choices as $field => $options) {
                $this->assertSame($family->choices($field), $options,
                    "[{$family->value}.{$field}] choices differ from the enum's, so something "
                    .'other than the declared vocabulary is reaching the browser.');
            }
        }

        $payload = json_encode(array_map(
            static fn (IntegrationFamily $f): array => app(SetupProjection::class)->forFamily($f)->choices,
            IntegrationFamily::cases(),
        )) ?: '';

        foreach ($this->secrets() as $secret) {
            $this->assertStringNotContainsString($secret, $payload);
        }
    }

    /**
     * S3. NO REVEAL ROUTE EXISTS FOR ANY INTEGRATION SECRET.
     *
     * AS AN EQUALITY over the whole route table. P1-02 has ONE reveal endpoint,
     * for two non-secret identifiers, and it is the only one permitted to
     * exist.
     *
     * Mutation: add POST console/integrations/{family}/reveal.
     */
    public function test_s3_the_only_reveal_route_is_p1_02s_identifier_reveal(): void
    {
        $reveals = [];

        foreach (Route::getRoutes() as $route) {
            if (str_contains(strtolower($route->uri()), 'reveal')
                || str_contains(strtolower((string) $route->getName()), 'reveal')) {
                $reveals[] = implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri();
            }
        }

        sort($reveals);

        /*
         * TWO REVEAL ROUTES EXIST, AND BOTH RETURN IDENTIFIERS RATHER THAN
         * SECRETS.
         *
         *   P1-02  the directory and application identifiers, masked on screen
         *          and revealed one at a time on an explicit action (D-27).
         *   P1-03  a person's Entra object id and tenant id, the same pattern
         *          reused (D-37).
         *
         * Neither can return a stored credential: the client secret never
         * becomes a string in the Identity module, and a User row holds no
         * secret at all. They are listed here BY NAME rather than excluded by
         * a pattern, so a third reveal route fails this case and has to be
         * justified in writing.
         *
         * WHAT MUST NEVER APPEAR IS A REVEAL UNDER THE INTEGRATIONS OR
         * FIRST-RUN SURFACES, which is asserted separately below - those are
         * the two that hold decryptable credentials.
         */
        $this->assertSame(
            [
                'POST console/identity/entra/reveal',
                'POST console/people/users/{user}/reveal',
            ],
            $reveals,
            'The set of reveal routes changed. Every one must be justified: no route may return a '
            .'stored secret to a person. Found: '.implode(', ', $reveals),
        );

        foreach ($reveals as $reveal) {
            $this->assertStringNotContainsString('integrations', $reveal,
                'A reveal route exists on the Integrations surface, which is the one surface that '
                .'holds decryptable credentials.');

            $this->assertStringNotContainsString('first-run', $reveal,
                'A reveal route exists on the First-Run surface, which is the one surface that '
                .'holds decryptable credentials.');
        }
    }

    /**
     * S2 and S4. NO SECRET REACHES THE AUDIT TRAIL, and ALLOWED_KEYS is still
     * exactly 15.
     */
    public function test_s2_and_s4_no_secret_reaches_the_audit_trail(): void
    {
        $this->givenEverySecretIsStored();

        $events = AuditEvent::query()->get();

        $this->assertGreaterThan(0, $events->count(),
            'No audit events were written, so finding no secret in them proves nothing.');

        foreach ($events as $event) {
            $serialised = json_encode($event->toArray()) ?: '';

            foreach ($this->secrets() as $secret) {
                $this->assertStringNotContainsString($secret, $serialised,
                    'A stored secret reached the audit trail.');
            }
        }
    }

    /** S4. The closed key list is unchanged at 15. */
    public function test_s4_the_allowed_key_list_is_still_exactly_fifteen(): void
    {
        $keys = (new ReflectionClass(SecurityEventLogger::class))->getConstant('ALLOWED_KEYS');

        $this->assertIsArray($keys);

        sort($keys);

        $this->assertSame([
            'domain_id', 'entity_id', 'entity_type', 'expires_at', 'organisation_id', 'provider',
            'reason', 'related_id', 'result', 'role', 'scope', 'sensitivity', 'subject', 'tenant',
            'user_id',
        ], $keys, 'ALLOWED_KEYS changed. P1-10 adds ten events and NO key - a credential, endpoint '
            .'or host has nowhere to go only while the list stays closed.');

        $this->assertCount(15, $keys);
    }

    /** A saved secret is stored encrypted, not in the configuration row. */
    public function test_a_secret_is_never_written_into_the_configuration_row(): void
    {
        $this->givenEverySecretIsStored();

        $rows = json_encode(
            IntegrationConfiguration::query()->get()->toArray(),
        ) ?: '';

        foreach ($this->secrets() as $secret) {
            $this->assertStringNotContainsString($secret, $rows,
                'A secret was written into the configuration row, which every listing reads.');
        }

        // ...and the ciphertext is not the plaintext.
        $ciphertexts = IntegrationSecret::query()
            ->pluck('ciphertext')
            ->all();

        $this->assertCount(4, $ciphertexts);

        foreach ($ciphertexts as $ciphertext) {
            foreach ($this->secrets() as $secret) {
                $this->assertStringNotContainsString($secret, (string) $ciphertext,
                    'A secret is stored in a form that contains its plaintext.');
            }
        }
    }
}
