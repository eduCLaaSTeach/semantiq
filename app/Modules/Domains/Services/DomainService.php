<?php

declare(strict_types=1);

namespace App\Modules\Domains\Services;

use App\Modules\Domains\Models\AccessExpectation;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainKind;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Domains\Models\DomainStatus;
use App\Modules\Domains\Support\BaselineDomains;
use App\Modules\Domains\Support\DomainViolation;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Shared\Lifecycle\PurgeDependencies;
use Illuminate\Support\Facades\DB;

/**
 * The lifecycle of a business domain.
 *
 * NOTHING HERE GRANTS ANYTHING. Enabling a domain does not open access;
 * disabling one does not close it; there is no access in P1-04 to open or
 * close. `status` describes AVAILABILITY AND READINESS - whether this
 * organisation says it is using this domain - and DomainsBoundaryTest asserts
 * that nothing anywhere reads it to decide what a person may see.
 *
 * THE LOCK ORDER IS DOMAIN -> OWNERSHIP -> DEPENDENCY CHECKS, and it is the
 * same order DomainOwnershipService uses, so two services cannot deadlock by
 * approaching the same two tables from opposite ends. Every operation that can
 * affect the D-42 invariant re-reads the domain under a row lock and re-checks
 * every rule INSIDE the transaction, because a rule checked before the
 * transaction opened has already been overtaken.
 */
final class DomainService
{
    public function __construct(
        private readonly SecurityEventLogger $events,
        private readonly DomainOwnershipService $ownership,
    ) {}

    /**
     * Create a CUSTOM domain. There is no path here that creates a baseline
     * one: the seven are product vocabulary and arrive through
     * BaselineDomainInitialiser.
     *
     * @param  array<string, string|null>  $attributes
     */
    public function create(Organisation $organisation, array $attributes, User $actor): BusinessDomain
    {
        $name = trim((string) ($attributes['name'] ?? ''));
        $code = mb_strtolower(trim((string) ($attributes['code'] ?? '')));

        return DB::transaction(function () use ($organisation, $name, $code, $attributes, $actor): BusinessDomain {
            $this->refuseIfCodeReserved($code);
            $this->refuseIfTaken($organisation->id, $name, $code);

            $domain = new BusinessDomain;

            $domain->forceFill([
                'organisation_id' => $organisation->id,
                'code' => $code,
                'name' => $name,
                'description' => $this->nullIfBlank($attributes['description'] ?? null),
                // kind is set HERE and read from nothing. A `kind` in a request
                // has nowhere to go - it is not sanitised out, it is not accepted.
                'kind' => DomainKind::Custom->value,
                // A new domain is not in use until somebody says it is, and
                // saying so requires naming an owner first (D-42).
                'status' => DomainStatus::Disabled->value,
                'access_expectation' => AccessExpectation::Undecided->value,
            ])->save();

            $this->record(SecurityEventLogger::BUSINESS_DOMAIN_CREATED, $domain, $actor, 'created');

            return $domain;
        });
    }

    /**
     * The organisation's words for a domain: its display name, what it covers,
     * and how widely it expects access to be given.
     *
     * NOT `code`, and NOT `kind`. Neither is a parameter of this method, so an
     * extra field in a request has nowhere to arrive.
     *
     * @param  array<string, string|null>  $attributes
     */
    public function update(BusinessDomain $domain, array $attributes, User $actor): BusinessDomain
    {
        $name = trim((string) ($attributes['name'] ?? ''));
        $expectation = (string) ($attributes['access_expectation'] ?? $domain->access_expectation->value);

        return DB::transaction(function () use ($domain, $name, $expectation, $attributes, $actor): BusinessDomain {
            $locked = $this->ownership->lockDomain($domain);

            $this->refuseIfTaken($locked->organisation_id, $name, null, $locked->getKey());

            $locked->fill([
                'name' => $name,
                'description' => $this->nullIfBlank($attributes['description'] ?? null),
                'access_expectation' => AccessExpectation::from($expectation)->value,
            ])->save();

            $this->record(SecurityEventLogger::BUSINESS_DOMAIN_UPDATED, $locked, $actor, 'updated');

            return $locked;
        });
    }

    /**
     * Say this organisation is using this domain - D-42.
     *
     * REFUSED WITHOUT A CURRENT, ACTIVE OWNER. Both conditions are re-checked
     * inside the transaction against the LOCKED domain and the LOCKED ownership
     * row, because an owner cleared between an outside check and this write
     * would commit "enabled with no owner", which is the one state D-42 exists
     * to make impossible.
     */
    public function enable(BusinessDomain $domain, User $actor): BusinessDomain
    {
        return DB::transaction(function () use ($domain, $actor): BusinessDomain {
            $locked = $this->ownership->lockDomain($domain);
            $current = $this->ownership->lockCurrentOwnership($locked);

            if ($current === null) {
                throw DomainViolation::ownerRequiredToEnable();
            }

            // Read from the database now, not from anything loaded earlier.
            $owner = User::query()->whereKey($current->user_id)->first();

            if ($owner === null || ! $owner->isActive()) {
                throw DomainViolation::ownerInactiveOnEnable();
            }

            $locked->forceFill(['status' => DomainStatus::Enabled->value])->save();

            $this->record(SecurityEventLogger::BUSINESS_DOMAIN_ENABLED, $locked, $actor, 'enabled');

            return $locked;
        });
    }

    /**
     * Say this organisation is not currently using this domain.
     *
     * NEVER REFUSED, whatever the owner state, and it removes nothing: not the
     * domain, not its owner, not its history. Disabling is how an unused
     * baseline domain is put away, and a safe action that can be refused stops
     * being one.
     */
    public function disable(BusinessDomain $domain, User $actor): BusinessDomain
    {
        return DB::transaction(function () use ($domain, $actor): BusinessDomain {
            $locked = $this->ownership->lockDomain($domain);

            $locked->forceFill(['status' => DomainStatus::Disabled->value])->save();

            $this->record(SecurityEventLogger::BUSINESS_DOMAIN_DISABLED, $locked, $actor, 'disabled');

            return $locked;
        });
    }

    /**
     * D-43's guarded purge, and it is deliberately narrow.
     *
     * A CUSTOM domain, which has NEVER had an owner, which nothing references.
     * It exists for the domain created by mistake five minutes ago and for
     * nothing else: once a domain has history, disable it instead.
     *
     * Conditions 2 and 3 agree by construction - every ownership period is a
     * row with a foreign key to the domain, so PurgeDependencies already
     * refuses any domain that ever had an owner. Condition 2 is checked
     * explicitly AS WELL, so the rule does not depend on anybody remembering
     * why the mechanism happens to work.
     */
    public function purge(BusinessDomain $domain, User $actor): void
    {
        // `kind` is immutable, so this one cannot go stale and costs no query.
        if ($domain->isBaseline()) {
            throw DomainViolation::baselineNotRemovable();
        }

        /*
         * THERE IS DELIBERATELY NO DEPENDENCY PRE-CHECK HERE.
         *
         * P1-03's group purge runs one before the transaction as a fast path.
         * Doing that here would read business_domain_owners BEFORE the domain
         * row is locked, which breaks the one lock order this unit promises -
         * domain, then ownership, then dependencies - and DomainConcurrencyTest
         * caught it. A fast refusal is not worth two orders.
         *
         * Nothing is lost: isPurgeable() still decides whether the screen offers
         * the control, and the check below is the guard either way.
         */
        DB::transaction(function () use ($domain, $actor): void {
            $locked = $this->ownership->lockDomain($domain);
            $this->ownership->lockCurrentOwnership($locked);

            if ($locked->isBaseline()) {
                throw DomainViolation::baselineNotRemovable();
            }

            // The second check, INSIDE the transaction and LOCKING, exactly as
            // D-24 requires: a dependency committed after this transaction
            // opened is the one this re-check exists to catch, and under MySQL's
            // REPEATABLE READ a plain SELECT would read a snapshot that misses it.
            $this->refuseIfInUse($locked, locking: true);

            $id = $locked->getKey();
            $organisationId = $locked->organisation_id;

            $locked->delete();

            $this->events->record(SecurityEventLogger::BUSINESS_DOMAIN_PURGED, [
                'entity_type' => 'business_domain',
                'entity_id' => $id,
                'organisation_id' => $organisationId,
                'user_id' => $actor->getKey(),
                'result' => 'purged',
            ]);
        });
    }

    /**
     * Whether the permanent-removal control may be offered at all.
     *
     * This is PRESENTATION. The guard is the re-check inside purge(), which
     * runs again with a locking read after the domain row is held.
     */
    public function isPurgeable(BusinessDomain $domain): bool
    {
        if ($domain->isBaseline()) {
            return false;
        }

        try {
            $this->refuseIfInUse($domain);

            return true;
        } catch (DomainViolation) {
            return false;
        }
    }

    /**
     * D-43 conditions 2 and 3.
     *
     * They agree by construction: every ownership period is a row with a
     * foreign key to the domain, so the schema-driven walk already refuses any
     * domain that ever had an owner. Condition 2 is ALSO checked explicitly, so
     * the rule does not depend on anybody remembering why the mechanism happens
     * to work - and so it keeps holding if that foreign key is ever restated.
     */
    private function refuseIfInUse(BusinessDomain $domain, bool $locking = false): void
    {
        $blockers = array_column(PurgeDependencies::blocking($domain, $locking), 'phrase');

        $everOwned = DomainOwnership::query()
            ->where('business_domain_id', $domain->getKey())
            ->when($locking, fn ($query) => $query->lockForUpdate())
            ->exists();

        if ($everOwned && $blockers === []) {
            $blockers[] = 'ownership history exists';
        }

        if ($blockers !== []) {
            throw DomainViolation::inUse($blockers);
        }
    }

    private function refuseIfCodeReserved(string $code): void
    {
        // Against the CLOSED baseline set, never against the rows present. A
        // deployment where `finance` is disabled, or absent, must still refuse
        // it - checking only the enabled rows is the version somebody who
        // misunderstood the rule would write.
        if (BaselineDomains::isReserved($code)) {
            throw DomainViolation::codeReserved();
        }
    }

    /**
     * A business sentence, not an integrity error.
     *
     * The unique constraints are the real guard. This exists so an
     * administrator who does the thing the screen invited them to do gets a
     * sentence instead of a constraint violation - the defect P1-03 shipped and
     * had to correct.
     *
     * The reads are LOCKING and inside the caller's transaction, so two
     * concurrent creations cannot both pass this check and then have one of
     * them meet the constraint.
     */
    private function refuseIfTaken(int $organisationId, string $name, ?string $code, ?int $ignoreId = null): void
    {
        $taken = fn (string $column, string $value): bool => BusinessDomain::query()
            ->where('organisation_id', $organisationId)
            ->where($column, $value)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->lockForUpdate()
            ->exists();

        if ($name !== '' && $taken('name', $name)) {
            throw DomainViolation::nameTaken();
        }

        if ($code !== null && $code !== '' && $taken('code', $code)) {
            throw DomainViolation::codeTaken();
        }
    }

    private function record(string $event, BusinessDomain $domain, User $actor, string $result): void
    {
        $this->events->record($event, [
            'entity_type' => 'business_domain',
            'entity_id' => $domain->getKey(),
            'organisation_id' => $domain->organisation_id,
            'user_id' => $actor->getKey(),
            'result' => $result,
        ]);
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
