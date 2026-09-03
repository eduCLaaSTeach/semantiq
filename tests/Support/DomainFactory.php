<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Domains\Models\AccessExpectation;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainKind;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Domains\Models\DomainStatus;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Models\User;
use Illuminate\Support\Carbon;

/**
 * Domain state built THROUGH THE MODELS, not through the services.
 *
 * The same choice OrganisationFactory and PeopleFactory made, for the same
 * reason: a test must be able to construct a state the services would REFUSE -
 * an enabled domain with no owner, an enabled domain whose owner is inactive,
 * two ownership periods on one day - and then assert that the services refuse
 * to act on it. A factory that used the services could only ever build states
 * the services already allow, which would make every guard look satisfied by
 * construction.
 */
final class DomainFactory
{
    public function domain(
        Organisation $organisation,
        string $name = 'Finance',
        string $code = 'finance',
        DomainKind $kind = DomainKind::Custom,
        DomainStatus $status = DomainStatus::Disabled,
        AccessExpectation $expectation = AccessExpectation::Undecided,
        ?string $description = null,
    ): BusinessDomain {
        $domain = new BusinessDomain;

        $domain->forceFill([
            'organisation_id' => $organisation->id,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'kind' => $kind->value,
            'status' => $status->value,
            'access_expectation' => $expectation->value,
        ])->save();

        return $domain;
    }

    /**
     * An ownership period, placed in time explicitly.
     *
     * assignedAt and endedAt are arguments so a test can build two periods on
     * one calendar day - the P1-01 collision - without waiting for a clock.
     */
    public function ownership(
        BusinessDomain $domain,
        User $user,
        ?Carbon $assignedAt = null,
        ?Carbon $endedAt = null,
    ): DomainOwnership {
        $period = new DomainOwnership;

        $period->forceFill([
            'business_domain_id' => $domain->id,
            'user_id' => $user->id,
            'assigned_at' => $assignedAt ?? now(),
            'ended_at' => $endedAt,
        ])->save();

        return $period;
    }

    /**
     * A domain that IS enabled and DOES have a current owner - the ordinary
     * healthy state, built directly so a test does not have to walk the whole
     * assign-then-enable sequence to arrive at it.
     */
    public function enabledWithOwner(Organisation $organisation, User $owner, string $name = 'Finance', string $code = 'finance'): BusinessDomain
    {
        $domain = $this->domain($organisation, $name, $code, status: DomainStatus::Enabled);

        $this->ownership($domain, $owner);

        return $domain->refresh();
    }
}
