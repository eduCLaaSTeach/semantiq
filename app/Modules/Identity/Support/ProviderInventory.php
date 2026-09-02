<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Platform\Identity\IdentityProvider;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Which identity providers this build actually binds.
 *
 * Deliberately a different question from ApprovedProviders::all(). This one is
 * about the code; that one is about a decision. Keeping them apart is what lets
 * a guard notice that something is registered which nobody approved - and a
 * provider nobody approved is an unapproved way into the product, which is worth
 * failing a build over.
 *
 * The container is asked through a tag rather than resolved directly, because
 * "give me the binding" cannot answer "what else is there?", and a second
 * provider added later would be invisible to exactly the guard meant to catch
 * it.
 */
final class ProviderInventory
{
    public function __construct(private readonly Container $container) {}

    /**
     * The key of every bound provider, sorted.
     *
     * @return list<string>
     */
    public function runtimeKeys(): array
    {
        $keys = [];

        foreach ($this->providers() as $provider) {
            $keys[] = $provider->key();
        }

        sort($keys);

        return array_values(array_unique($keys));
    }

    /** Bound, approved, and reporting itself configured. */
    public function isConfigured(string $key): bool
    {
        foreach ($this->providers() as $provider) {
            if ($provider->key() === $key) {
                return $provider->isConfigured();
            }
        }

        return false;
    }

    /** Runtime providers with no entry in the approved catalogue. */
    public function unapprovedKeys(): array
    {
        return array_values(array_filter(
            $this->runtimeKeys(),
            fn (string $key): bool => ! ApprovedProviders::isApproved($key),
        ));
    }

    /** @return list<IdentityProvider> */
    private function providers(): array
    {
        try {
            $tagged = $this->container->tagged('identity.providers');
        } catch (Throwable) {
            return [];
        }

        $providers = [];

        foreach ($tagged as $provider) {
            if ($provider instanceof IdentityProvider) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }
}
