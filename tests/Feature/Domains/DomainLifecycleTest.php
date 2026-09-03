<?php

declare(strict_types=1);

namespace Tests\Feature\Domains;

use App\Modules\Domains\Models\AccessExpectation;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainKind;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Domains\Models\DomainStatus;
use App\Modules\Domains\Services\BaselineDomainInitialiser;
use App\Modules\Domains\Services\DomainOwnershipService;
use App\Modules\Domains\Services\DomainService;
use App\Modules\Domains\Support\DomainViolation;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DomainFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * Creating, renaming, enabling, disabling and removing a domain.
 *
 * The rules that hold this unit together are all here: the identity code never
 * changes, the baseline set is closed, enabling requires somebody accountable,
 * and disabling can never be refused.
 */
final class DomainLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private DomainFactory $domains;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->domains = new DomainFactory;
    }

    /**
     * N8. The identity code cannot be edited, on any route.
     *
     * `code` is not in $fillable and is not a parameter of update(). An extra
     * field in the request has NOWHERE TO ARRIVE - it is not sanitised out, it
     * is simply not accepted.
     *
     * Mutation: add 'code' to BusinessDomain::$fillable, or to the validated
     * fields of DomainController::update().
     */
    public function test_the_identity_code_cannot_be_changed(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $domain = $this->domains->domain($organisation, 'Sales', 'sales', DomainKind::Baseline);

        $this->actingAsUser($admin)->put("/console/domains/{$domain->id}", [
            'name' => 'Commercial',
            'code' => 'commercial',
            'access_expectation' => 'undecided',
        ])->assertRedirect();

        $domain->refresh();

        // The Product Owner's own worked example: the name is theirs, the
        // identity is SemantIQ's, and every later unit still joins to `sales`.
        $this->assertSame('Commercial', $domain->name, 'The display name did not change.');
        $this->assertSame('sales', $domain->code, 'The identity code was changed.');
    }

    /**
     * N9. `kind` posted in a request is ignored.
     *
     * Mutation: accept kind from input in create() or update().
     */
    public function test_kind_cannot_be_set_from_a_request(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->actingAsUser($admin)->post('/console/domains', [
            'name' => 'Pretender',
            'code' => 'pretender',
            'kind' => 'baseline',
        ])->assertRedirect();

        $domain = BusinessDomain::query()->where('code', 'pretender')->sole();

        $this->assertSame(DomainKind::Custom, $domain->kind, 'A request set kind to baseline.');

        // And it cannot be flipped afterwards either.
        $this->actingAsUser($admin)->put("/console/domains/{$domain->id}", [
            'name' => 'Pretender',
            'access_expectation' => 'undecided',
            'kind' => 'baseline',
        ]);

        $this->assertSame(DomainKind::Custom, $domain->fresh()->kind);
    }

    /**
     * N10. A reserved baseline code is refused EVEN WHEN THAT BASELINE DOMAIN
     * IS DISABLED - and even when it is absent from this deployment entirely.
     *
     * The check runs against the closed catalogue, not against the rows
     * present. Checking only the enabled rows is the version somebody who
     * misunderstood the rule would write, and it is the mutation.
     *
     * Mutation: check BusinessDomain rows with status enabled instead of
     * BaselineDomains::isReserved().
     */
    public function test_a_reserved_code_is_refused_even_when_that_domain_is_disabled_or_absent(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        // Present but DISABLED.
        $this->domains->domain($organisation, 'Finance', 'finance', DomainKind::Baseline);

        $this->actingAsUser($admin)->post('/console/domains', [
            'name' => 'Our finance',
            'code' => 'finance',
        ])->assertSessionHasErrors('domains');

        // ABSENT entirely - no `learning` row exists in this test.
        $this->actingAsUser($admin)->post('/console/domains', [
            'name' => 'Our learning',
            'code' => 'learning',
        ])->assertSessionHasErrors('domains');

        $this->assertSame(1, BusinessDomain::query()->count(), 'A reserved code was accepted.');
    }

    /**
     * N11 and N52. A duplicate is refused IN BUSINESS LANGUAGE.
     *
     * P1-03 shipped a path that handed the administrator a database integrity
     * error for doing exactly what the test script told them to do. The
     * constraint is still the real guard; this is what makes the refusal
     * readable.
     *
     * Mutation: remove refuseIfTaken() and let the unique constraint surface.
     */
    public function test_a_duplicate_name_or_code_is_refused_in_business_language(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->domains->domain($organisation, 'Delivery', 'delivery');

        $attempts = [
            'A domain called that already exists. Open it, or choose another name.' => [
                'name' => 'Delivery', 'code' => 'delivery-two',
            ],
            'That code is already used by another domain.' => [
                'name' => 'Delivery Two', 'code' => 'delivery',
            ],
        ];

        foreach ($attempts as $expected => $payload) {
            $response = $this->actingAsUser($admin)->post('/console/domains', $payload);

            $response->assertSessionHasErrors('domains');

            // Read immediately: the errors bag is the session's, and the next
            // request would overwrite it.
            $message = (string) $response->getSession()->get('errors')->first('domains');

            $this->assertSame($expected, $message);

            // The whole point: what reaches the administrator is a sentence,
            // and not one word of the constraint really holding the line.
            foreach (['SQLSTATE', 'Integrity constraint', 'unique', 'UNIQUE', 'violation', 'PDO'] as $leak) {
                $this->assertStringNotContainsString(
                    $leak,
                    $message,
                    'A database error reached the administrator.'
                );
            }
        }

        $this->assertSame(1, BusinessDomain::query()->count());
    }

    /**
     * N13. There is no route that creates or removes a BASELINE domain.
     *
     * Mutation: allow purge() to accept a baseline domain.
     */
    public function test_a_baseline_domain_cannot_be_removed(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $domain = $this->domains->domain($organisation, 'Finance', 'finance', DomainKind::Baseline);

        $this->actingAsUser($admin)
            ->delete("/console/domains/{$domain->id}")
            ->assertSessionHasErrors('domains');

        $this->assertDatabaseHas('business_domains', ['id' => $domain->id]);

        $this->assertSame(
            'Standard domains cannot be removed. Disable it instead.',
            (string) $this->app['session.store']->get('errors')->first('domains')
        );
    }

    /**
     * N29. Enabling a domain with no owner is refused, and the refusal names
     * the remedy.
     *
     * Mutation: drop the check; or check that an ownership row exists EVER
     * rather than a CURRENT one, which passes for a domain whose owner was
     * cleared.
     */
    public function test_enabling_a_domain_with_no_owner_is_refused(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $former = $this->make->user($organisation);

        $never = $this->domains->domain($organisation, 'Never owned', 'never-owned');

        // And a domain that HAS had an owner, whose period has ended. An
        // "ever owned" check would wrongly let this one through.
        $formerly = $this->domains->domain($organisation, 'Formerly owned', 'formerly-owned');
        $this->domains->ownership($formerly, $former, now()->subDay(), now()->subHour());

        foreach ([$never, $formerly] as $domain) {
            $this->actingAsUser($admin)
                ->patch("/console/domains/{$domain->id}/enable")
                ->assertSessionHasErrors('domains');

            $this->assertSame(
                DomainStatus::Disabled,
                $domain->fresh()->status,
                'A domain with nobody accountable for it was enabled.'
            );
        }

        $this->assertSame(
            'Assign an owner before enabling this domain. Someone has to be accountable for it.',
            (string) session('errors')?->first('domains')
        );
    }

    /**
     * N30. Enabling a domain whose current owner is INACTIVE is refused.
     *
     * The state is reachable: P1-03 may deactivate an owner at any time, and
     * P1-04 does not get to refuse that. What P1-04 does refuse is turning such
     * a domain ON.
     *
     * Mutation: check that a current owner exists without checking they are
     * active.
     */
    public function test_enabling_a_domain_whose_owner_is_inactive_is_refused(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);
        $this->domains->ownership($domain, $owner);

        $owner->forceFill(['status' => 'inactive'])->save();

        $this->actingAsUser($admin)
            ->patch("/console/domains/{$domain->id}/enable")
            ->assertSessionHasErrors('domains');

        $this->assertSame(DomainStatus::Disabled, $domain->fresh()->status);

        $this->assertSame(
            'This domain\'s owner is no longer active. Assign an active owner before enabling it.',
            (string) session('errors')?->first('domains')
        );
    }

    /**
     * The permitted path, so the two refusals above are not passing merely
     * because enable() never works.
     */
    public function test_a_domain_with_an_active_owner_can_be_enabled(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);
        $this->domains->ownership($domain, $owner);

        $this->actingAsUser($admin)
            ->patch("/console/domains/{$domain->id}/enable")
            ->assertRedirect();

        $this->assertSame(DomainStatus::Enabled, $domain->fresh()->status);
    }

    /**
     * N32 and N33. DISABLING IS NEVER REFUSED, and it removes nothing.
     *
     * A safe action that can be refused stops being a safe action. Disabling is
     * how an unused baseline domain is put away, and how an administrator gets
     * out of every state this unit can reach.
     *
     * Mutation: add any condition to disable(); or have disable() clear the
     * owner.
     */
    public function test_disabling_is_never_refused_and_removes_nothing(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $withActiveOwner = $this->domains->enabledWithOwner($organisation, $owner, 'Finance', 'finance');

        $inactiveOwner = $this->make->user($organisation);
        $withInactiveOwner = $this->domains->enabledWithOwner($organisation, $inactiveOwner, 'Sales', 'sales');
        $inactiveOwner->forceFill(['status' => 'inactive'])->save();

        // An enabled domain with NO owner at all - unreachable through the
        // screens, built directly, and it must still disable.
        $ownerless = $this->domains->domain($organisation, 'Orphan', 'orphan', status: DomainStatus::Enabled);

        foreach ([$withActiveOwner, $withInactiveOwner, $ownerless] as $domain) {
            $this->actingAsUser($admin)
                ->patch("/console/domains/{$domain->id}/disable")
                ->assertSessionHasNoErrors();

            $this->assertSame(DomainStatus::Disabled, $domain->fresh()->status);
        }

        // Nothing was removed by any of it.
        $this->assertSame(2, DomainOwnership::query()->whereNull('ended_at')->count());
        $this->assertSame(3, BusinessDomain::query()->count());
    }

    /**
     * N44 and N45. The guarded purge is narrow, and ownership history blocks it.
     *
     * D-43: once a domain has history, disable rather than purge. The two
     * conditions agree by construction - an ownership row is a foreign key
     * reference, so the schema walk already refuses - and BOTH are checked, so
     * the rule does not depend on anybody remembering why.
     *
     * Mutation: check only CURRENT ownership; or drop the explicit check and
     * rely on the walk alone, then remove the foreign key.
     */
    public function test_a_custom_domain_is_purgeable_only_before_it_has_ever_had_an_owner(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $fresh = $this->domains->domain($organisation, 'Mistake', 'mistake');

        // History only - the period has ENDED, so a "current owner" check would
        // wrongly allow this one to be destroyed along with its history.
        $formerlyOwned = $this->domains->domain($organisation, 'Was owned', 'was-owned');
        $this->domains->ownership($formerlyOwned, $owner, now()->subDay(), now()->subHour());

        $this->actingAsUser($admin)
            ->delete("/console/domains/{$formerlyOwned->id}")
            ->assertSessionHasErrors('domains');

        $this->assertDatabaseHas('business_domains', ['id' => $formerlyOwned->id]);

        $this->actingAsUser($admin)
            ->delete("/console/domains/{$fresh->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('business_domains', ['id' => $fresh->id]);
    }

    /**
     * N14. Every operation the DESIGN names exists as a service method.
     *
     * The P1-01 presence guard: an operation that does not exist has no test to
     * fail, which is how a delivered unit shipped missing Update on four entity
     * types.
     *
     * Mutation: delete any one of these methods.
     */
    public function test_every_named_operation_exists(): void
    {
        $expected = [
            DomainService::class => ['create', 'update', 'enable', 'disable', 'purge', 'isPurgeable'],
            DomainOwnershipService::class => [
                'set', 'clear', 'currentOwner', 'lockDomain', 'lockCurrentOwnership',
            ],
            BaselineDomainInitialiser::class => ['initialise'],
        ];

        foreach ($expected as $service => $methods) {
            foreach ($methods as $method) {
                $this->assertTrue(
                    method_exists($service, $method),
                    "[{$service}::{$method}()] does not exist. The DESIGN names it as a required operation."
                );
            }
        }
    }

    /**
     * N15. Every write confirms itself.
     *
     * P1-01 shipped a refusal channel with no success channel, and the Product
     * Owner reported "after Click Save nothing happens" on a screen where the
     * save had worked every time.
     *
     * Mutation: return a bare redirect from any write.
     */
    public function test_every_write_confirms_itself(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation, 'Delivery', 'delivery');

        $writes = [
            ['post', '/console/domains', ['name' => 'New', 'code' => 'new'], 'Domain created.'],
            ['put', "/console/domains/{$domain->id}", ['name' => 'Delivery', 'access_expectation' => 'broad'], 'Domain updated.'],
            ['patch', "/console/domains/{$domain->id}/owner", ['user_id' => $owner->id], 'Owner assigned.'],
            ['patch', "/console/domains/{$domain->id}/enable", [], 'Domain enabled.'],
            ['patch', "/console/domains/{$domain->id}/disable", [], 'Domain disabled.'],
            ['patch', "/console/domains/{$domain->id}/owner/clear", [], 'Owner cleared.'],
        ];

        foreach ($writes as [$method, $uri, $payload, $expected]) {
            $response = $this->actingAsUser($admin)->call($method, $uri, $payload);

            $response->assertSessionHasNoErrors();

            $this->assertSame(
                $expected,
                $response->getSession()->get('confirmation'),
                "[{$method} {$uri}] did not confirm itself."
            );
        }
    }

    /**
     * N12. Renaming leaves every reference to the domain unchanged.
     *
     * Ownership rows point at the id, and the code is what later units will
     * join to. Neither moves when the display name does.
     */
    public function test_renaming_a_domain_moves_no_reference(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation, 'Sales', 'sales', DomainKind::Baseline);
        $period = $this->domains->ownership($domain, $owner);

        $this->actingAsUser($admin)->put("/console/domains/{$domain->id}", [
            'name' => 'Commercial',
            'access_expectation' => 'limited',
        ])->assertRedirect();

        $this->assertSame($domain->id, $period->fresh()->business_domain_id);
        $this->assertSame('sales', $domain->fresh()->code);
        $this->assertSame(AccessExpectation::Limited, $domain->fresh()->access_expectation);
    }

    /**
     * A domain created through the screen starts disabled, unowned and
     * undecided - the same starting point the baseline seven get.
     */
    public function test_a_new_custom_domain_starts_disabled_and_unowned(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->actingAsUser($admin)->post('/console/domains', [
            'name' => 'Partnerships',
            'code' => 'partnerships',
        ])->assertRedirect();

        $domain = BusinessDomain::query()->where('code', 'partnerships')->sole();

        $this->assertSame(DomainStatus::Disabled, $domain->status);
        $this->assertSame(AccessExpectation::Undecided, $domain->access_expectation);
        $this->assertNull($domain->currentOwnership);
    }

    /** The service refuses directly, not only through the controller. */
    public function test_the_service_refuses_a_reserved_code(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->expectException(DomainViolation::class);

        app(DomainService::class)->create($organisation, ['name' => 'Ours', 'code' => 'executive'], $admin);
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
