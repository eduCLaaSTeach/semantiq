<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Identity;

use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Models\PlatformSetting;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use Illuminate\Support\Facades\Schema;

/**
 * WHERE THE IDENTITY CONFIGURATION COMES FROM. The single answer, for the
 * runtime provider, the health check, the screens and the commands alike.
 *
 * Before this class those four asked different questions. Ten call sites read
 * config('identity.microsoft.*') directly - including four inside a
 * missingKeys() array, in a shape a grep for the call would not have found -
 * so a deployment running from the store would have had its health screen
 * cheerfully describing .env. A health screen that reports on a source the
 * sign-in path does not use is worse than no health screen: it is confidently
 * wrong at the moment somebody is debugging.
 *
 * TWO BRANCHES AND NO THIRD.
 *
 *   env    pre-cutover, or a deployment that has not moved. Read the
 *          environment. THE ONLY STATE IN WHICH .env IS AUTHORITY.
 *   store  post-cutover. Read the store. .env IS NOT CONSULTED AT ALL.
 *
 * THERE IS NO "TRY THE STORE, FALL BACK TO .env". Not as a ??, not as an
 * `?: `, not as an if-empty. A silent fallback is how two credential
 * authorities come to coexist, and it fails in the worst possible way: the
 * store is edited, the old .env value keeps working, everything looks correct,
 * and nobody finds out until the .env secret expires - at which point the
 * deployment breaks for a reason that has not been true for months.
 * EnvIsNotIdentityAuthorityAfterCutover asserts the store branch reads no
 * env() and that no null-coalesce couples the two.
 *
 * A MISSING VALUE IN `store` MODE IS AN EMPTY STRING, NOT A FALLBACK. Empty is
 * reported as missing by IdentityConfiguration::missingKeys(), which is the
 * truthful answer: the store is the authority and the authority has nothing.
 *
 * RESOLVED FRESH ON EVERY CALL, NEVER CACHED. The container used to bake the
 * tenant into three singletons at first resolution, so an administrator who
 * changed the tenant and tested it in the same request validated the PREVIOUS
 * configuration and was told it worked. Caching here would restore that defect
 * one layer up.
 */
final class IdentityConfigurationSource
{
    public const FAMILY = 'identity';

    public const SECRET_CLIENT = 'client_secret';

    /**
     * The STORE branch only. See resolve().
     */
    private ?IdentityConfiguration $storedResolution = null;

    public function __construct(private readonly IntegrationSecretStore $secrets) {}

    /**
     * THE STORE BRANCH IS MEMOISED FOR THE REQUEST. THE ENV BRANCH IS NOT.
     *
     * That asymmetry is not an oversight - it follows the authority. The
     * provider bindings are bind() rather than singleton(), so one sign-in
     * request builds EntraDiscovery, IdTokenValidator and EntraProvider
     * separately and each asks for the configuration:
     *
     *   env    the authority is the process's configuration array, already
     *          resolved once per process. Re-reading it costs nothing, and it
     *          is the only way an in-process change to config() can be observed
     *          at all - which is exactly what a test that alters one identity
     *          value and asserts the health row does.
     *
     *   store  the authority is the database. Re-reading it is two queries per
     *          provider, and within one request it cannot change underneath a
     *          caller except by that caller's own write - which calls forget().
     *
     * MEMOISATION HERE DOES NOT REINTRODUCE THE STALENESS DEFECT bind() fixed.
     * That defect was an instance built from LAST REQUEST'S configuration
     * surviving into this one; a value cached for one request cannot do it. The
     * save-then-test-in-one-request path does not come through here at all:
     * ProviderProbe builds its own provider from the CANDIDATE configuration,
     * which is the only honest way to test something not yet committed.
     */
    public function resolve(): IdentityConfiguration
    {
        /*
         * A DEPLOYMENT THAT HAS NOT MIGRATED CANNOT HAVE CUT OVER.
         *
         * The only thing that can set identity_source to `store` is a
         * successful commit, which requires this table. So when the table is
         * absent the authority is `env` - a fact about the deployment, not a
         * fallback. The difference matters: a query that FAILS is not caught
         * here and propagates, so a store-backed deployment whose database is
         * unreachable refuses to resolve an identity configuration rather than
         * quietly reverting to .env and signing people in against a directory
         * it stopped using.
         */
        if (! Schema::hasTable('platform_settings')) {
            return $this->fromEnvironment(PlatformSetting::unsavedDefault());
        }

        if ($this->storedResolution !== null) {
            return $this->storedResolution;
        }

        $settings = PlatformSetting::current();

        if (! $settings->identityReadsStore()) {
            return $this->fromEnvironment($settings);
        }

        return $this->storedResolution = $this->fromStore($settings);
    }

    /**
     * Discard the memoised store resolution.
     *
     * CALLED BY EVERY WRITER, without exception. It is what makes memoising the
     * store branch safe: a caller that changes the configuration and then acts
     * on it in the same request - the First-Run commit, which sets
     * identity_source and renders the next step - must not be answered from
     * before its own write.
     */
    public function forget(): void
    {
        $this->storedResolution = null;
    }

    /**
     * The pre-cutover authority. config() reads config/identity.php, which
     * reads the server environment.
     */
    private function fromEnvironment(PlatformSetting $settings): IdentityConfiguration
    {
        return new IdentityConfiguration(
            tenantId: (string) config('identity.microsoft.tenant_id'),
            clientId: (string) config('identity.microsoft.client_id'),
            clientSecret: (string) config('identity.microsoft.client_secret'),
            redirectUri: (string) config('identity.microsoft.redirect_uri'),
            source: PlatformSetting::SOURCE_ENV,
            revision: $settings->identity_config_revision,
        );
    }

    /**
     * The post-cutover authority.
     *
     * NOT ONE config() CALL, NOT ONE env() CALL, AND NOTHING THAT COULD BECOME
     * ONE. What is absent here is absent, and is reported as missing.
     */
    private function fromStore(PlatformSetting $settings): IdentityConfiguration
    {
        $row = IntegrationConfiguration::query()
            ->where('family', self::FAMILY)
            ->first();

        $stored = is_array($row?->settings) ? $row->settings : [];

        return new IdentityConfiguration(
            tenantId: $this->string($stored, 'tenant_id'),
            clientId: $this->string($stored, 'client_id'),
            clientSecret: (string) $this->secrets->get(self::FAMILY, self::SECRET_CLIENT),
            redirectUri: $this->string($stored, 'redirect_uri'),
            source: PlatformSetting::SOURCE_STORE,
            revision: $settings->identity_config_revision,
        );
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function string(array $stored, string $key): string
    {
        $value = $stored[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
