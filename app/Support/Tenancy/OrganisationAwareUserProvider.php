<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The user provider used by the session guard, with the organisation scope
 * deliberately lifted for identity lookups only.
 *
 * There is a genuine circularity here, and it is worth stating plainly because
 * the fix looks like a hole in the boundary otherwise:
 *
 *   - Every request resolves the signed-in user by primary key.
 *   - The organisation context is derived FROM that user.
 *   - So while the user is being resolved, no context exists yet.
 *
 * With the scope applied, that lookup matches nothing, the guard concludes
 * nobody is signed in, and no session can ever be established. Authentication
 * would be permanently broken in a way that looks like a login bug.
 *
 * Lifting the scope here is safe because these methods are reached only by the
 * guard, and only with a session identifier, a remember token or credentials
 * the caller already holds. None of them lets one organisation enumerate or
 * fish for another's users.
 *
 * The `User` model keeps the scope everywhere else, so the Users administration
 * screen and every ordinary query stay bounded.
 *
 * Requirement IDs: NFR-SEC-02, FR-AUTH-001.
 */
class OrganisationAwareUserProvider extends EloquentUserProvider
{
    /**
     * Resolve by primary key, which is what the session guard does per request.
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->unscoped(fn () => parent::retrieveById($identifier));
    }

    /**
     * Resolve by the remember-me token.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return $this->unscoped(fn () => parent::retrieveByToken($identifier, $token));
    }

    /**
     * Resolve by credentials.
     *
     * Unused while identity is federated to Entra, since SemantIQ holds no
     * local credential, but overridden for completeness so a future local guard
     * cannot reintroduce the circularity by accident.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        return $this->unscoped(fn () => parent::retrieveByCredentials($credentials));
    }

    /**
     * Run one identity lookup with the organisation scope lifted.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function unscoped(callable $callback): mixed
    {
        return app(OrganisationContext::class)->withoutScoping($callback);
    }
}
