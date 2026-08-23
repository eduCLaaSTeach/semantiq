<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\Organisation;
use App\Modules\Platform\Enums\LifecycleStatus;
use RuntimeException;

/**
 * The one answer to "whose data is this".
 *
 * CLAUDE.md requires that customer-owned configuration, metadata, audit data and
 * policy records carry an explicit organisation context, so that a future
 * multi-tenant service can isolate customers without a redesign, and that
 * cross-organisation access is denied by default.
 *
 * Every scoped read and write asks this class. It is registered as a singleton,
 * so within one request there is exactly one answer and it cannot change
 * halfway through a transaction.
 *
 * FAILS CLOSED. When the context cannot be resolved, `currentId()` returns null
 * and `BelongsToOrganisation` turns that into "no rows" and "no writes" rather
 * than "all rows". The failure mode of a missing scope must be an empty screen
 * that somebody investigates, never another customer's data.
 *
 * Resolution order, first match wins:
 *
 *  1. An explicit binding. Console commands, queued jobs and tests have no
 *     signed-in person, so they say which organisation they are acting for.
 *  2. The single active organisation, when the instance holds exactly one.
 *     This is the documented single-customer deployment baseline. The moment a
 *     second organisation exists this stops resolving, which is deliberate: a
 *     multi-tenant instance must bind its context explicitly rather than
 *     inherit a guess made for a single-tenant one.
 *
 * The signed-in person's own organisation becomes step 2 in gate 2, when
 * `users.organisation_id` exists. Until then no code may assume it.
 */
class OrganisationContext
{
    private ?Organisation $bound = null;

    /** Memoised step 2, so one request does not re-query per scoped model. */
    private ?Organisation $resolved = null;

    private bool $resolvedAttempted = false;

    /**
     * Act as a given organisation for the rest of this request or command.
     */
    public function bind(Organisation $organisation): void
    {
        $this->bound = $organisation;
    }

    /**
     * Drop the binding, returning to automatic resolution.
     *
     * Used by tests asserting the fail-closed path, and by any long-running
     * process that handles work for more than one organisation in turn.
     */
    public function forget(): void
    {
        $this->bound = null;
        $this->resolved = null;
        $this->resolvedAttempted = false;
    }

    /**
     * The organisation in force, or null when it cannot be resolved.
     */
    public function current(): ?Organisation
    {
        if ($this->bound !== null) {
            return $this->bound;
        }

        if (! $this->resolvedAttempted) {
            $this->resolvedAttempted = true;
            $this->resolved = $this->resolveSingleActive();
        }

        return $this->resolved;
    }

    /**
     * The organisation id in force, or null. This is what the global scope
     * compares against, and null is what makes it match nothing.
     */
    public function currentId(): ?int
    {
        return $this->current()?->id;
    }

    /**
     * The organisation in force, or an exception.
     *
     * For write paths, where continuing without a scope would produce an
     * unattributable row. An unattributable row in an audit table is worse than
     * no row, because it looks like evidence.
     *
     * @throws RuntimeException
     */
    public function require(): Organisation
    {
        $organisation = $this->current();

        if ($organisation === null) {
            throw new RuntimeException(
                'No organisation context is in force. A scoped write was refused rather than '
                .'attributed to the wrong customer.'
            );
        }

        return $organisation;
    }

    /**
     * The single active organisation, when there is exactly one.
     *
     * `count() === 1` rather than `first()`. On an instance holding two
     * organisations, `first()` would silently pick one and every scoped query
     * in the request would run against the wrong customer - the exact failure
     * this class exists to make impossible.
     */
    private function resolveSingleActive(): ?Organisation
    {
        $active = Organisation::query()
            ->where('status', LifecycleStatus::Active->value)
            ->limit(2)
            ->get();

        return $active->count() === 1 ? $active->first() : null;
    }
}
