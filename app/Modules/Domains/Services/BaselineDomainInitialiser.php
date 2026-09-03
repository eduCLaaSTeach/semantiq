<?php

declare(strict_types=1);

namespace App\Modules\Domains\Services;

use App\Modules\Domains\Models\AccessExpectation;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainKind;
use App\Modules\Domains\Models\DomainStatus;
use App\Modules\Domains\Support\BaselineDomains;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;

/**
 * The seven baseline domains, materialised once an organisation exists - D-46.
 *
 * THE INITIAL STATE IS THE DECISION, not an implementation detail:
 *
 *   status              DISABLED
 *   owner               NONE - no ownership row is created
 *   access_expectation  undecided
 *   description         null
 *
 * "Do not pretend the organisation uses every baseline domain simply because
 * SemantIQ knows the vocabulary." That is why they arrive disabled, and it is
 * what makes the D-42 enable rule coherent: ENABLING IS AN ACT, by an
 * administrator who has decided the organisation uses this domain and has named
 * somebody accountable for it. Arriving enabled would make "Enabled" mean
 * nothing on the first screen anybody sees.
 *
 * IDEMPOTENT, KEYED ON CODE. Running it twice produces seven domains, not
 * fourteen. Running it after an administrator has renamed, enabled or assigned
 * an owner to a domain CHANGES NONE OF THAT - it is not a reset, and a row that
 * already exists is left exactly as it is.
 *
 * THIS IS NOT A MIGRATION AND NOT A READ PATH. A migration writes structure and
 * runs before any organisation exists, so it could not name one. And nothing
 * here is reachable from a GET: "materialise on first view" looks convenient
 * and quietly turns a read path into a write path that races itself under two
 * administrators. It is called from exactly two places, both explicit, both
 * asserted: OrganisationService::createProfile(), and domains:initialise.
 */
final class BaselineDomainInitialiser
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    /**
     * Create whichever of the seven are missing. Returns the codes created, in
     * catalogue order, so a caller can report what it actually did rather than
     * assert that something happened.
     *
     * The actor is nullable: the one-time production run has no administrator
     * behind it, and inventing one to fill the field would put a false name in
     * the security log.
     *
     * @return list<string>
     */
    public function initialise(Organisation $organisation, ?User $actor = null): array
    {
        $existing = BusinessDomain::query()
            ->where('organisation_id', $organisation->getKey())
            ->pluck('code')
            ->all();

        $created = [];

        foreach (BaselineDomains::CATALOGUE as $code => $name) {
            if (in_array($code, $existing, true)) {
                // Already present. Nothing is written and NOTHING IS RECORDED -
                // an event for something that did not happen is a false line in
                // an audit trail.
                continue;
            }

            $domain = new BusinessDomain;

            $domain->forceFill([
                'organisation_id' => $organisation->getKey(),
                'code' => $code,
                'name' => $name,
                'description' => null,
                'kind' => DomainKind::Baseline->value,
                'status' => DomainStatus::Disabled->value,
                'access_expectation' => AccessExpectation::Undecided->value,
            ])->save();

            $this->events->record(SecurityEventLogger::BUSINESS_DOMAIN_CREATED, [
                'entity_type' => 'business_domain',
                'entity_id' => $domain->getKey(),
                'organisation_id' => $organisation->getKey(),
                'user_id' => $actor?->getKey(),
                // Distinguishable from an administrator's own creation, which
                // is the only reason these two share an event name.
                'result' => 'initialised',
            ]);

            $created[] = $code;
        }

        return $created;
    }
}
