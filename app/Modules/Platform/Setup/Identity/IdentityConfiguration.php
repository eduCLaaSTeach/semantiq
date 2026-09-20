<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Identity;

/**
 * One resolved identity configuration.
 *
 * Deliberately NOT named IdentityResolver or anything near it:
 * App\Modules\Platform\Identity\IdentityResolver already exists and does
 * something entirely different - it maps an already-verified external identity
 * to an existing User and never creates one (SYS-014, SYS-015). Two unrelated
 * jobs behind one word in one namespace is how the wrong one gets edited.
 *
 * A VALUE, NOT A SERVICE. It is produced once per resolution and thrown away,
 * so nothing can hold yesterday's tenant. That matters because the container
 * used to bake the tenant into three singletons at first resolution, which made
 * "save a new tenant and test it in the same request" validate the PREVIOUS
 * configuration and report success.
 */
final readonly class IdentityConfiguration
{
    public function __construct(
        public string $tenantId,
        public string $clientId,
        public string $clientSecret,
        public string $redirectUri,
        /**
         * Which authority answered: PlatformSetting::SOURCE_ENV or
         * SOURCE_STORE. Carried so a screen can say where the deployment reads
         * from without a second lookup that might answer differently.
         */
        public string $source,
        /**
         * The revision the identity health cache keys are namespaced by. A
         * configuration change increments it, which makes the previous
         * tenant's stored result unreadable rather than merely unwanted.
         */
        public int $revision,
    ) {}

    /**
     * The environment variable names that are absent, for the unconfigured
     * empty state.
     *
     * KEY NAMES, NEVER VALUES - the value is by definition absent, which is the
     * whole finding. The names stay MICROSOFT_* even when the source is the
     * store, because they are what an operator recognises; the answer is about
     * the RESOLVED source either way, so a deployment running from the store is
     * never told that .env is missing something.
     *
     * @return list<string>
     */
    public function missingKeys(): array
    {
        $missing = [];

        foreach ([
            'MICROSOFT_TENANT_ID' => $this->tenantId,
            'MICROSOFT_CLIENT_ID' => $this->clientId,
            'MICROSOFT_CLIENT_SECRET' => $this->clientSecret,
            'MICROSOFT_REDIRECT_URI' => $this->redirectUri,
        ] as $name => $value) {
            if ($value === '') {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    public function isComplete(): bool
    {
        return $this->missingKeys() === [];
    }
}
