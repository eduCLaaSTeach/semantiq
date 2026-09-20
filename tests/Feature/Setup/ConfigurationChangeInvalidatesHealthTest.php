<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\Identity\Health\IdentityHealthReport;
use App\Modules\Identity\Support\IdentityConfigurationReport;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Models\PlatformSetting;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\BrokenCacheStore;
use Tests\TestCase;

/**
 * H1 - H6. CORRECTION 5: A GREEN RESULT MUST NOT OUTLIVE THE CONFIGURATION IT
 * TESTED.
 *
 * `status` and `last_tested_at` sit on the configuration row and nothing
 * cleared them, so:
 *
 *     test SMTP against smtp.old.example  ->  Available, tested 14:02
 *     change the host and the password
 *       ->  the card still reads "Available - last tested 14:02"
 *
 * The timestamp is TRUE and the claim it supports is FALSE. It is the same
 * failure class P1-09's storedReport() correction fixed - a stored result
 * presenting as current evidence - arriving through a different door.
 *
 * H5 IS THE CASE THAT DISTINGUISHES THIS CORRECTION FROM THE OBVIOUS FIX. A
 * Cache::forget()-only implementation passes H4 and fails H5.
 */
final class ConfigurationChangeInvalidatesHealthTest extends TestCase
{
    use RefreshDatabase;

    private function writer(): IntegrationConfigurationWriter
    {
        return app(IntegrationConfigurationWriter::class);
    }

    private function row(IntegrationFamily $family): IntegrationConfiguration
    {
        return IntegrationConfiguration::query()->where('family', $family->value)->firstOrFail();
    }

    private function givenEmailTestedAvailable(): void
    {
        $this->writer()->save(IntegrationFamily::Email, [
            'host' => 'smtp.old.example',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'postmaster@old.example',
            'from_address' => 'noreply@old.example',
            'from_name' => 'SemantIQ',
        ]);

        $this->writer()->recordTestResult(
            IntegrationFamily::Email,
            HealthStatus::Available,
            'The mail server accepted the connection and the credentials.',
        );

        $row = $this->row(IntegrationFamily::Email);
        $this->assertSame(HealthStatus::Available->value, $row->status);
        $this->assertNotNull($row->last_tested_at);
    }

    /**
     * H1. A meaningful field change clears BOTH the status and the timestamp.
     *
     * Mutation: clear the status but keep last_tested_at. The card then reads
     * "Not checked - last tested 14:02", which is a timestamp with no statement
     * attached and is exactly what misleads.
     */
    public function test_h1_changing_the_host_clears_the_status_and_the_timestamp(): void
    {
        $this->givenEmailTestedAvailable();

        $this->writer()->save(IntegrationFamily::Email, ['host' => 'smtp.new.example']);

        $row = $this->row(IntegrationFamily::Email);

        $this->assertSame(HealthStatus::NotChecked->value, $row->status,
            'The card still claims Available for a configuration that has never been tested.');

        $this->assertNull($row->last_tested_at,
            'last_tested_at survived the change it describes. The timestamp is accurate and the '
            .'claim it appears to support is false.');

        $this->assertNull($row->explanation);
    }

    /** H2. A SECRET-only change invalidates too. */
    public function test_h2_changing_only_the_password_invalidates_the_result(): void
    {
        $this->givenEmailTestedAvailable();

        $this->writer()->save(IntegrationFamily::Email, [], ['password' => 'a-new-password']);

        $row = $this->row(IntegrationFamily::Email);

        $this->assertSame(HealthStatus::NotChecked->value, $row->status,
            'A new password left the previous successful authentication showing as current.');
        $this->assertNull($row->last_tested_at);
    }

    /** ...and a presentation-only change does NOT. */
    public function test_h2_changing_a_display_only_field_does_not_invalidate(): void
    {
        $this->givenEmailTestedAvailable();

        $this->writer()->save(IntegrationFamily::Email, ['from_name' => 'SemantIQ Notifications']);

        $row = $this->row(IntegrationFamily::Email);

        $this->assertSame(HealthStatus::Available->value, $row->status,
            'Renaming the sender cleared a result it cannot affect. Clearing results for changes '
            .'that do not matter trains administrators to ignore Not checked.');
        $this->assertNotNull($row->last_tested_at);
    }

    /**
     * H3. Only an explicit test may set a positive status.
     *
     * Mutation: let save() write Available. A save can then leave a card
     * looking green for a configuration nobody has checked.
     */
    public function test_h3_a_save_can_never_produce_a_positive_status(): void
    {
        foreach ([IntegrationFamily::Email, IntegrationFamily::Ai, IntegrationFamily::Fabric] as $family) {
            $this->writer()->save($family, []);

            $this->assertSame(
                HealthStatus::NotChecked->value,
                $this->row($family)->status,
                "[{$family->value}] reports a status other than Not checked after a save alone.",
            );
        }

        // ...and the source says so structurally: exactly one method writes a
        // positive status, and it is the one the tests call.
        $source = (string) file_get_contents(
            (new \ReflectionClass(IntegrationConfigurationWriter::class))->getFileName(),
        );

        $positiveWriters = preg_match_all('/HealthStatus::(Available|Degraded|Unavailable)/', $source);

        $this->assertSame(0, $positiveWriters,
            'The configuration writer names a positive status somewhere. Only recordTestResult(), '
            .'which takes the status from the adapter that ran the test, may write one.');
    }

    /**
     * H4. IDENTITY: changing the tenant makes storedReport() say Not checked,
     * NOT the previous tenant's result.
     *
     * Mutation: keep the un-revisioned cache key. This must fail.
     */
    public function test_h4_changing_the_tenant_makes_the_stored_identity_result_unreadable(): void
    {
        $check = app(IdentityHealthCheck::class);

        Cache::put($check->resultKey(), [
            'state' => IdentityHealthReport::HEALTHY,
            'at' => now()->subMinutes(5)->toIso8601String(),
        ], now()->addDay());

        $this->assertSame(IdentityHealthReport::HEALTHY, $check->storedReport()->state,
            'The stored result is not readable to begin with, so this case proves nothing.');

        $this->writer()->save(IntegrationFamily::Identity, [
            'tenant_id' => '99999999-9999-9999-9999-999999999999',
        ]);

        // A FRESH CHECK, as a later request would build.
        $after = app(IdentityHealthCheck::class);

        $this->assertSame(IdentityHealthReport::NOT_CHECKED, $after->storedReport()->state,
            'THE PREVIOUS TENANT\'S HEALTH IS BEING SHOWN AS THE CURRENT ONE, with a timestamp '
            .'that makes it look trustworthy.');

        $this->assertNull($after->storedReport()->checkedAt);
    }

    /** The revision really moved, and it is what the key is built from. */
    public function test_h4_the_revision_is_what_changes_the_key(): void
    {
        $before = app(IdentityHealthCheck::class)->resultKey();
        $revisionBefore = PlatformSetting::current()->identity_config_revision;

        $this->writer()->save(IntegrationFamily::Identity, ['client_id' => 'a-new-application']);

        $after = app(IdentityHealthCheck::class)->resultKey();

        $this->assertNotSame($before, $after);
        $this->assertSame($revisionBefore + 1, PlatformSetting::current()->identity_config_revision);
        $this->assertStringEndsWith(':'.($revisionBefore + 1), $after);
    }

    /**
     * H5. THE CASE THAT DISTINGUISHES REVISION BINDING FROM A forget().
     *
     * The cache accepts forget(), reports success, and removes nothing - a
     * FORGETFUL store, which is what a full or misconfigured cache actually
     * does, and what a file cache does when an unlink fails. Production runs a
     * file cache.
     *
     * Mutation: rely on Cache::forget() alone - delete the revision from
     * resultKey(). H4 still passes because forget() works against an ordinary
     * cache. H5 FAILS, which is the whole reason it exists.
     */
    public function test_h5_invalidation_survives_a_cache_that_ignores_forget(): void
    {
        // Stores and returns values perfectly, and never removes one - what a
        // FILE cache does when the unlink fails. Production runs a file cache.
        $store = new BrokenCacheStore(BrokenCacheStore::IGNORES_FORGET);

        // The REAL repository wrapping it, so put/get/forget go through the
        // code the application runs rather than a double of it.
        Cache::swap(new Repository($store));

        $check = app(IdentityHealthCheck::class);
        $keyBefore = $check->resultKey();

        Cache::put($keyBefore, [
            'state' => IdentityHealthReport::HEALTHY,
            'at' => now()->subMinutes(5)->toIso8601String(),
        ], now()->addDay());

        $this->assertSame(IdentityHealthReport::HEALTHY, $check->storedReport()->state,
            'The seeded result is not readable, so this case proves nothing.');

        $this->writer()->save(IntegrationFamily::Identity, [
            'tenant_id' => '77777777-7777-7777-7777-777777777777',
        ]);

        // THE PREMISE OF THE CASE: the old entry is STILL THERE, and still
        // readable. forget() was called, reported success, and removed nothing.
        $this->assertContains($keyBefore, $store->forgotten,
            'The old key was never offered to forget(), so the housekeeping half is missing.');

        $this->assertNotNull(Cache::get($keyBefore),
            'The old entry was removed after all, so this cache is not ignoring forget() and the '
            .'case is not exercising what it claims to.');

        // AND THE PREVIOUS TENANT'S RESULT IS STILL UNREACHABLE, because
        // nobody asks for that key any more.
        $after = app(IdentityHealthCheck::class);

        $this->assertNotSame($keyBefore, $after->resultKey());

        $this->assertSame(IdentityHealthReport::NOT_CHECKED, $after->storedReport()->state,
            'A cache that ignores forget() is serving the PREVIOUS tenant\'s health as current. '
            .'This is why the mechanism is a revision boundary and not a best-effort forget().');
    }

    /**
     * H6. The health screen and /auth/microsoft resolve the SAME source.
     *
     * Mutation: restore any one of the eleven direct config('identity.microsoft.*')
     * reads. NoDirectIdentityConfigRead catches the source; this catches the
     * behaviour.
     */
    public function test_h6_the_health_check_and_the_sign_in_path_read_one_source(): void
    {
        config([
            'identity.microsoft.tenant_id' => 'the-env-tenant',
            'identity.microsoft.client_id' => 'the-env-application',
            'identity.microsoft.client_secret' => 'the-env-secret',
            'identity.microsoft.redirect_uri' => route('auth.microsoft.callback'),
        ]);

        $this->writer()->save(IntegrationFamily::Identity, [
            'tenant_id' => 'the-stored-tenant',
            'client_id' => 'the-stored-application',
            'redirect_uri' => route('auth.microsoft.callback'),
        ], ['client_secret' => 'the-stored-secret']);

        $settings = PlatformSetting::forUpdate();
        $settings->identity_source = PlatformSetting::SOURCE_STORE;
        $settings->save();

        app(IdentityConfigurationSource::class)->forget();

        $identity = app(IdentityConfigurationSource::class)->resolve();

        $this->assertSame('the-stored-tenant', $identity->tenantId,
            'The resolved configuration is .env on a deployment whose authority is the store.');

        // The screen's read model resolves the same way.
        $report = IdentityConfigurationReport::build(
            app(IdentityProvider::class),
            app(IdentityConfigurationSource::class),
        );

        $this->assertStringNotContainsString('env', strtolower($report->directoryMasked),
            'The screen is describing the environment while sign-in uses the store.');

        $this->assertSame([], $report->missingKeys,
            'The unconfigured empty state names environment variables a store-backed deployment is '
            .'right not to have.');
    }
}
