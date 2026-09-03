<?php

declare(strict_types=1);

namespace App\Modules\Domains\Services;

use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Domains\Support\DomainViolation;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Support\Facades\DB;

/**
 * Who is accountable for a domain, and when they were.
 *
 * ACCOUNTABILITY ONLY. Setting an owner writes NOTHING to the users table - not
 * platform_role, not a group, not a membership, not any column. The owner of
 * Finance cannot necessarily see Finance, and that sentence is on the screen
 * rather than left for a reader to discover.
 *
 * THE SERIALISATION BOUNDARY IS THE DOMAIN ROW.
 *
 * The first design locked the open ownership row. That is not a boundary in the
 * case that matters most: when a domain has NO current owner there is NO ROW TO
 * LOCK, so two concurrent first-owner assignments both see "nobody owns this"
 * and both insert. It is also the wrong object - every operation here decides
 * from the domain's STATUS and its CURRENT OWNERSHIP together, and a lock on
 * one cannot serialise a decision taken over both.
 *
 * So: lock the business_domains row first, then the ownership row, then run any
 * dependency checks. DOMAIN -> OWNERSHIP -> DEPENDENCIES, the same order in
 * DomainService, so two services cannot deadlock by approaching the same two
 * tables from opposite ends.
 *
 * The domain is RE-READ from the database under that lock rather than trusted
 * from the route-bound instance, which is a REPEATABLE READ snapshot taken
 * before the lock existed. Deciding from it would make the lock decorative.
 */
final class DomainOwnershipService
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    /**
     * Set the accountable person - whether or not somebody already holds it.
     *
     * ONE OPERATION AND ONE TRANSACTION, never "clear, then assign". That
     * sequence passes through an ownerless state, which on an enabled domain is
     * a state clear() refuses, so an implementation built that way would either
     * refuse a legitimate change or special-case its way around its own rule.
     */
    public function set(BusinessDomain $domain, User $owner, User $actor): DomainOwnership
    {
        return DB::transaction(function () use ($domain, $owner, $actor): DomainOwnership {
            $locked = $this->lockDomain($domain);
            $current = $this->lockCurrentOwnership($locked);

            $this->refuseIfNotEligible($locked, $owner);

            // Re-assigning the same person is a no-op. Two adjacent periods for
            // one person would be history recording nothing that happened.
            if ($current !== null && $current->user_id === $owner->getKey()) {
                return $current;
            }

            $now = now();

            $current?->forceFill(['ended_at' => $now])->save();

            $next = new DomainOwnership;
            $next->forceFill([
                'business_domain_id' => $locked->getKey(),
                'user_id' => $owner->getKey(),
                'assigned_at' => $now,
                'ended_at' => null,
            ])->save();

            $this->record(SecurityEventLogger::BUSINESS_DOMAIN_OWNER_ASSIGNED, $locked, $actor, $owner);

            return $next;
        });
    }

    /**
     * End the current ownership, leaving nobody accountable.
     *
     * Refused while the domain is ENABLED - D-42. An enabled domain is one the
     * organisation says it is using, and something it is using has somebody
     * answerable for it. Disable it, or name a replacement.
     */
    public function clear(BusinessDomain $domain, User $actor): void
    {
        DB::transaction(function () use ($domain, $actor): void {
            $locked = $this->lockDomain($domain);
            $current = $this->lockCurrentOwnership($locked);

            if ($locked->isEnabled()) {
                throw DomainViolation::ownerRequiredWhileEnabled();
            }

            if ($current === null) {
                return;
            }

            $owner = $current->user_id;

            $current->forceFill(['ended_at' => now()])->save();

            $this->events->record(SecurityEventLogger::BUSINESS_DOMAIN_OWNER_CLEARED, [
                'entity_type' => 'business_domain',
                'entity_id' => $locked->getKey(),
                'organisation_id' => $locked->organisation_id,
                'user_id' => $actor->getKey(),
                'related_id' => $owner,
                'result' => 'cleared',
            ]);
        });
    }

    /**
     * The current owner, or null. Read from the OPEN ROW and nowhere else.
     */
    public function currentOwner(BusinessDomain $domain): ?User
    {
        return $domain->currentOwnership()->with('user')->first()?->user;
    }

    /**
     * The domain row, re-read under a lock. This is the boundary.
     *
     * Public so DomainService uses exactly the same first step, in exactly the
     * same order, rather than writing its own and drifting.
     */
    public function lockDomain(BusinessDomain $domain): BusinessDomain
    {
        return BusinessDomain::query()
            ->whereKey($domain->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** The open ownership period, locked. May legitimately be null. */
    public function lockCurrentOwnership(BusinessDomain $locked): ?DomainOwnership
    {
        return DomainOwnership::query()
            ->where('business_domain_id', $locked->getKey())
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->first();
    }

    /**
     * The eligible-owner rules, D-45 and D-16.
     *
     * An INACTIVE user cannot be NEWLY assigned: naming somebody who cannot
     * sign in as accountable is a fiction. A current owner who is LATER
     * deactivated is a different question and is answered in P1-03's favour -
     * see DomainService::enable() and the Needs attention state.
     */
    private function refuseIfNotEligible(BusinessDomain $domain, User $owner): void
    {
        if ($owner->organisation_id === null || $owner->organisation_id !== $domain->organisation_id) {
            throw DomainViolation::ownerOutsideOrganisation();
        }

        if (! $owner->isActive()) {
            throw DomainViolation::ownerNotActive();
        }
    }

    private function record(string $event, BusinessDomain $domain, User $actor, User $owner): void
    {
        $this->events->record($event, [
            'entity_type' => 'business_domain',
            'entity_id' => $domain->getKey(),
            'organisation_id' => $domain->organisation_id,
            'user_id' => $actor->getKey(),
            'related_id' => $owner->getKey(),
            'result' => 'assigned',
        ]);
    }
}
