<?php

declare(strict_types=1);

namespace Tests\Feature\Domains;

use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Domains\Models\DomainStatus;
use App\Modules\Domains\Services\DomainOwnershipService;
use App\Modules\Domains\Support\DomainViolation;
use App\Modules\People\Services\UserDirectoryService;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\DomainFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * Who is accountable for a domain, when they were, and what that is worth.
 *
 * What it is worth is NOTHING - DomainAccessBoundaryTest proves that
 * behaviourally. This file is about the record itself: one current owner, never
 * two; history that survives every later decision; and the rule that P1-04 must
 * never make P1-03's deactivation unsafe.
 */
final class DomainOwnershipTest extends TestCase
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
     * N3c. THERE IS NO owner_user_id COLUMN ON business_domains.
     *
     * This fails on the column's EXISTENCE, not on two values disagreeing. A
     * second writable source of truth for one fact is wrong even during the
     * period it happens to agree - by the time they disagree, nothing in the
     * schema says which is right.
     *
     * Mutation: add the column and have setOwner() write both.
     */
    public function test_there_is_no_owner_column_on_the_domain(): void
    {
        $columns = Schema::getColumnListing('business_domains');

        $this->assertNotContains(
            'owner_user_id',
            $columns,
            'business_domains carries an owner column. The ownership table is the single source of '
            .'truth for who owns a domain, and a column beside it would be a second one.'
        );

        foreach ($columns as $column) {
            $this->assertStringNotContainsString(
                'owner',
                $column,
                "[business_domains.{$column}] holds ownership, which belongs in business_domain_owners."
            );
        }
    }

    /**
     * N26. The current owner is read from the OPEN ROW and nowhere else.
     *
     * Mutation: answer currentOwner() from anything but the ended_at IS NULL row.
     */
    public function test_the_current_owner_is_the_open_period(): void
    {
        $organisation = $this->make->organisation();
        $first = $this->make->user($organisation);
        $second = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);

        $this->domains->ownership($domain, $first, now()->subDays(2), now()->subDay());
        $this->domains->ownership($domain, $second, now()->subDay());

        $this->assertSame(
            $second->id,
            app(DomainOwnershipService::class)->currentOwner($domain->refresh())?->id
        );
    }

    /**
     * N21. Changing owner RETAINS the previous period. It is never updated in
     * place, and the two periods do not overlap.
     *
     * Mutation: have set() update the existing row's user_id.
     */
    public function test_changing_the_owner_retains_the_previous_period(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $first = $this->make->user($organisation);
        $second = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);

        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner", ['user_id' => $first->id]);
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner", ['user_id' => $second->id]);

        $periods = DomainOwnership::query()
            ->where('business_domain_id', $domain->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $periods, 'The previous ownership period was not retained.');

        $this->assertSame($first->id, $periods[0]->user_id);
        $this->assertNotNull($periods[0]->ended_at, 'The outgoing period was not ended.');

        $this->assertSame($second->id, $periods[1]->user_id);
        $this->assertNull($periods[1]->ended_at);

        $this->assertSame(
            1,
            DomainOwnership::query()->where('business_domain_id', $domain->id)->whereNull('ended_at')->count(),
            'More than one ownership period is open.'
        );
    }

    /**
     * N28. Re-assigning the SAME person is a no-op.
     *
     * Two adjacent periods for one person would be history recording nothing
     * that happened.
     *
     * Mutation: always insert.
     */
    public function test_reassigning_the_same_person_creates_no_new_period(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);

        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner", ['user_id' => $owner->id]);
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner", ['user_id' => $owner->id]);
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner", ['user_id' => $owner->id]);

        $this->assertSame(
            1,
            DomainOwnership::query()->where('business_domain_id', $domain->id)->count(),
            'Re-assigning the same person churned the ownership history.'
        );
    }

    /**
     * N23. TWO OWNERSHIP PERIODS ON ONE CALENDAR DAY are both recorded.
     *
     * The P1-01 collision, refused at the schema this time. Under a
     * (domain, assigned_at) key over DATE values, the second period here
     * carries the same key values as the first and the database would refuse it
     * with an integrity error about something the administrator did not do
     * wrong. Production produced exactly this case for group membership on its
     * first day of use.
     *
     * Mutation: make assigned_at a DATE, or add UNIQUE(business_domain_id,
     * assigned_at).
     */
    public function test_two_ownership_periods_on_one_day_are_both_recorded(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $first = $this->make->user($organisation);
        $second = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);

        $morning = now()->startOfDay()->addHours(9);
        $afternoon = now()->startOfDay()->addHours(14);

        $this->travelTo($morning);
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner", ['user_id' => $first->id]);

        $this->travelTo($afternoon);
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner", ['user_id' => $second->id]);

        $this->travelBack();

        $periods = DomainOwnership::query()->where('business_domain_id', $domain->id)->orderBy('id')->get();

        $this->assertCount(2, $periods);

        $this->assertSame(
            $periods[0]->assigned_at->toDateString(),
            $periods[1]->assigned_at->toDateString(),
            'The two periods did not land on the same calendar day, so this proves nothing.'
        );

        $this->assertNotSame(
            $periods[0]->assigned_at->toDateTimeString(),
            $periods[1]->assigned_at->toDateTimeString(),
            'The two periods are indistinguishable in time, which is the P1-01 collision.'
        );
    }

    /**
     * N22. NO ROUTE DELETES AN OWNERSHIP PERIOD.
     *
     * Ending sets ended_at; the row is the evidence somebody was accountable.
     *
     * Mutation: add a DELETE route for a period, or have clear() delete the row.
     */
    public function test_no_route_deletes_an_ownership_period(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);

        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner", ['user_id' => $owner->id]);
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner/clear");

        $period = DomainOwnership::query()->where('business_domain_id', $domain->id)->sole();

        $this->assertNotNull($period->ended_at, 'Clearing the owner did not end the period.');
        $this->assertSame($owner->id, $period->user_id, 'The record of who was accountable was rewritten.');
    }

    /**
     * N24. An INACTIVE user cannot be NEWLY assigned.
     *
     * Naming somebody who cannot sign in as accountable is a fiction.
     *
     * Mutation: allow it.
     */
    public function test_an_inactive_user_cannot_be_newly_assigned(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $inactive = $this->make->user($organisation);

        $inactive->forceFill(['status' => 'inactive'])->save();

        $domain = $this->domains->domain($organisation);

        $response = $this->actingAsUser($admin)
            ->patch("/console/domains/{$domain->id}/owner", ['user_id' => $inactive->id]);

        $response->assertSessionHasErrors('domains');

        $this->assertSame(
            'That person\'s account is not active. Choose someone who can sign in.',
            (string) $response->getSession()->get('errors')->first('domains')
        );

        $this->assertSame(0, DomainOwnership::query()->count());
    }

    /**
     * N18 and N19. Owners come from THIS organisation, and a user with no
     * organisation fails closed.
     *
     * Mutation: drop the same-organisation check; let NULL pass.
     */
    public function test_an_owner_must_belong_to_this_organisation(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $admin = $this->make->user($ours, administrator: true);
        $outsider = $this->make->user($theirs);
        $unassigned = $this->make->user();

        $domain = $this->domains->domain($ours);

        foreach ([$outsider, $unassigned] as $candidate) {
            $this->actingAsUser($admin)
                ->patch("/console/domains/{$domain->id}/owner", ['user_id' => $candidate->id])
                ->assertSessionHasErrors('domains');
        }

        $this->assertSame(0, DomainOwnership::query()->count());
    }

    /**
     * N31. CLEARING THE OWNER OF AN ENABLED DOMAIN IS REFUSED; clearing it
     * while disabled is permitted.
     *
     * Both halves matter. A guard that refused in both states would break the
     * only way out of the state, and one that allowed both would leave an
     * enabled domain with nobody accountable - the state D-42 exists to make
     * impossible.
     *
     * Mutation: allow it in both states; refuse it in both states.
     */
    public function test_clearing_the_owner_is_refused_while_enabled_and_permitted_while_disabled(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->enabledWithOwner($organisation, $owner);

        $refused = $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner/clear");

        $refused->assertSessionHasErrors('domains');

        $this->assertSame(
            'This domain is enabled. Assign a replacement owner, or disable it first.',
            (string) $refused->getSession()->get('errors')->first('domains')
        );

        $this->assertSame(1, DomainOwnership::query()->whereNull('ended_at')->count());

        // The way out that the refusal names.
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/disable")->assertSessionHasNoErrors();
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner/clear")->assertSessionHasNoErrors();

        $this->assertSame(0, DomainOwnership::query()->whereNull('ended_at')->count());
    }

    /**
     * The OTHER way out: replace the owner rather than disable the domain. The
     * refusal names both, so both must work.
     */
    public function test_an_enabled_domains_owner_can_be_replaced_without_disabling_it(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $outgoing = $this->make->user($organisation);
        $incoming = $this->make->user($organisation);

        $domain = $this->domains->enabledWithOwner($organisation, $outgoing);

        $this->actingAsUser($admin)
            ->patch("/console/domains/{$domain->id}/owner", ['user_id' => $incoming->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(DomainStatus::Enabled, $domain->fresh()->status, 'Replacing the owner disabled the domain.');

        $this->assertSame(
            $incoming->id,
            app(DomainOwnershipService::class)->currentOwner($domain->refresh())?->id
        );
    }

    /**
     * N25. THE ONE THAT MATTERS MOST FOR P1-03.
     *
     * Deactivating a user who owns domains is NEVER refused by P1-04, their
     * ownership is untouched, and their domains stay enabled.
     *
     * If this rule were a continuous invariant instead of a transition check,
     * the only way to keep it true would be to refuse this deactivation - which
     * D-36 forbids, because it makes a safe action unsafe, and would mean an
     * administrator could not offboard somebody without first hunting through
     * domains.
     *
     * Mutation: have the owner check refuse the deactivation; have deactivation
     * clear the ownership; have it disable the domains.
     */
    public function test_deactivating_a_domain_owner_is_never_blocked(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $first = $this->domains->enabledWithOwner($organisation, $owner, 'Finance', 'finance');
        $second = $this->domains->enabledWithOwner($organisation, $owner, 'Sales', 'sales');
        $third = $this->domains->enabledWithOwner($organisation, $owner, 'People', 'people');

        app(UserDirectoryService::class)->deactivate($owner, $admin);

        $this->assertFalse($owner->fresh()->isActive(), 'The deactivation did not happen.');

        foreach ([$first, $second, $third] as $domain) {
            $this->assertSame(
                DomainStatus::Enabled,
                $domain->fresh()->status,
                'A domain was silently disabled when its owner was deactivated.'
            );

            $this->assertSame(
                $owner->id,
                app(DomainOwnershipService::class)->currentOwner($domain->refresh())?->id,
                'Deactivating a user cleared their domain ownership.'
            );
        }
    }

    /**
     * The drift that creates - surfaced, not prevented.
     *
     * N24c: the domain shows Needs attention, and it is STILL ENABLED.
     */
    public function test_an_inactive_owner_is_surfaced_and_the_domain_stays_enabled(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->enabledWithOwner($organisation, $owner);

        $owner->forceFill(['status' => 'inactive'])->save();

        $props = $this->actingAsUser($admin)->get("/console/domains/{$domain->id}")
            ->viewData('page')['props'];

        $this->assertTrue($props['domain']['needsAttention'], 'An inactive owner was not surfaced.');
        $this->assertSame('enabled', $props['domain']['status'], 'The domain was disabled by a side effect.');

        // And the list surfaces it too, with a filter that finds it.
        $list = $this->actingAsUser($admin)->get('/console/domains?owner=attention')
            ->viewData('page')['props'];

        $this->assertCount(1, $list['domains']['data']);
        $this->assertTrue($list['domains']['data'][0]['needsAttention']);
    }

    /**
     * N20. The invariant is held by a LOCKING READ INSIDE the transaction.
     *
     * MEASURED AGAINST A BASELINE, not against transactionLevel() > 0. Under
     * RefreshDatabase every test already runs inside a transaction, so that
     * comparison is true whatever the service does - which is exactly how two
     * P1-03 mutations survived while looking guarded.
     *
     * Mutation: move the locking reads outside DB::transaction(); remove them.
     */
    public function test_ownership_is_written_inside_a_transaction_the_service_opened(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);

        $baseline = DB::transactionLevel();
        $observed = null;

        DB::listen(function ($query) use (&$observed, $baseline): void {
            if (str_contains(strtolower($query->sql), 'insert into "business_domain_owners"')
                || str_contains(strtolower($query->sql), 'insert into `business_domain_owners`')) {
                $observed = DB::transactionLevel() - $baseline;
            }
        });

        app(DomainOwnershipService::class)->set($domain, $owner, $admin);

        $this->assertNotNull($observed, 'No ownership insert was observed, so this proves nothing.');

        $this->assertGreaterThan(
            0,
            $observed,
            'The ownership write did not happen inside a transaction this service opened.'
        );
    }

    /** The service refuses directly, not only through the controller. */
    public function test_the_service_refuses_clearing_an_enabled_domains_owner(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->enabledWithOwner($organisation, $owner);

        $this->expectException(DomainViolation::class);

        app(DomainOwnershipService::class)->clear($domain, $admin);
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
