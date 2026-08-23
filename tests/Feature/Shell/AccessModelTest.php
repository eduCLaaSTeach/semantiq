<?php

declare(strict_types=1);

namespace Tests\Feature\Shell;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Models\DomainEntitlement;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The two-dimension access model from doc/ROLE_MODEL.md.
 *
 * The claim worth testing hardest is section 1's: a role alone NEVER grants
 * business data. It is the rule most likely to be quietly broken by a later
 * change, because "the system administrator can see everything" is such a
 * natural assumption to code.
 */
class AccessModelTest extends TestCase
{
    use RefreshDatabase;

    private function personOn(Role $role, bool $auditor = false): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);
        $user->forceFill(['role' => $role, 'is_auditor' => $auditor])->save();

        return $user->refresh();
    }

    private function entitle(User $user, BusinessDomain ...$domains): User
    {
        foreach ($domains as $domain) {
            DomainEntitlement::query()->create(['user_id' => $user->id, 'domain' => $domain->value]);
        }

        return $user->refresh();
    }

    #[Test]
    public function a_system_administrator_holds_no_business_domain_by_default(): void
    {
        $admin = $this->personOn(Role::SystemAdmin);

        // The rule the whole model rests on. Being the highest tier says what
        // somebody may DO, never which business information they may do it to.
        $this->assertSame([], $admin->entitledDomains());
        $this->assertFalse($admin->isEntitledTo(BusinessDomain::Finance));
        $this->assertFalse(app(Navigation::class)->allows($admin, 'domain-finance'));
    }

    #[Test]
    public function the_highest_tier_still_needs_the_entitlement(): void
    {
        $admin = $this->entitle($this->personOn(Role::SystemAdmin), BusinessDomain::Sales);
        $navigation = app(Navigation::class);

        $this->assertTrue($navigation->allows($admin, 'domain-sales'));
        $this->assertFalse($navigation->allows($admin, 'domain-people'));
    }

    #[Test]
    public function the_lowest_tier_reaches_a_domain_it_is_entitled_to(): void
    {
        $viewer = $this->entitle($this->personOn(Role::Viewer), BusinessDomain::Learning);

        // Entitlement is not a second ranking. A Viewer entitled to Learning
        // sees Learning; the tier governs what they may do there.
        $this->assertTrue(app(Navigation::class)->allows($viewer, 'domain-learning'));
    }

    #[Test]
    public function only_the_entitled_domains_appear_in_the_rail(): void
    {
        $person = $this->entitle($this->personOn(Role::Analyst), BusinessDomain::Sales, BusinessDomain::Finance);

        $workspace = app(Navigation::class)->for($person)['Workspace'];
        $intelligence = collect($workspace)->firstWhere('label', 'My Intelligence');

        $this->assertSame(
            ['Sales Intelligence', 'Finance Intelligence'],
            collect($intelligence['children'])->pluck('label')->all(),
        );
    }

    #[Test]
    public function my_intelligence_survives_as_a_page_when_no_domain_is_entitled(): void
    {
        $person = $this->personOn(Role::Analyst);

        $workspace = app(Navigation::class)->for($person)['Workspace'];
        $intelligence = collect($workspace)->firstWhere('label', 'My Intelligence');

        // It degrades to a leaf rather than disappearing: that page is exactly
        // where somebody with no domains is told domains are granted separately.
        $this->assertNotNull($intelligence);
        $this->assertArrayNotHasKey('children', $intelligence);
        $this->assertSame('intelligence', $intelligence['route']);
    }

    #[Test]
    public function entitled_domains_come_back_as_domains_in_the_enum_order(): void
    {
        // Regression: pluck() applies the model cast, so this returns enum
        // instances. Comparing them to ->value strictly matched nothing, and
        // the only symptom was an empty domain grid on a page that should have
        // shown one - invisible to every test that went through the rail.
        $person = $this->entitle(
            $this->personOn(Role::Analyst),
            BusinessDomain::Learning,
            BusinessDomain::Sales,
        );

        $domains = $person->entitledDomains();

        $this->assertContainsOnlyInstancesOf(BusinessDomain::class, $domains);

        // Enum order, not grant order, so the interface does not reshuffle
        // itself as entitlements are added.
        $this->assertSame([BusinessDomain::Sales, BusinessDomain::Learning], $domains);
    }

    #[Test]
    public function the_intelligence_page_shows_a_card_for_each_entitled_domain(): void
    {
        $person = $this->entitle($this->personOn(Role::Analyst), BusinessDomain::Sales);

        $this->actingAs($person)
            ->get('/intelligence')
            ->assertOk()
            ->assertSee('Sales Intelligence')
            ->assertDontSee('Finance Intelligence')
            ->assertDontSee('No domains assigned');
    }

    #[Test]
    public function the_intelligence_page_explains_itself_when_nothing_is_entitled(): void
    {
        $this->actingAs($this->personOn(Role::Analyst))
            ->get('/intelligence')
            ->assertOk()
            ->assertSee('No domains assigned')
            ->assertSee('granted separately from your platform role');
    }

    #[Test]
    public function a_sensitive_domain_is_marked_as_carrying_restricted_fields(): void
    {
        $person = $this->entitle($this->personOn(Role::DomainOwner), BusinessDomain::People);

        // Recorded on the domain rather than left to each screen to remember.
        $this->assertTrue(BusinessDomain::People->isSensitive());
        $this->assertFalse(BusinessDomain::Sales->isSensitive());

        $this->actingAs($person)->get('/intelligence')->assertSee('Restricted fields');
    }

    #[Test]
    public function an_auditor_reaches_compliance_without_holding_the_tier(): void
    {
        $auditor = $this->personOn(Role::Viewer, auditor: true);
        $navigation = app(Navigation::class);

        // The reason Auditor is a flag and not a rung: compliance evidence
        // without any of the operational authority the tier would carry.
        $this->assertTrue($navigation->allows($auditor, 'compliance'));
        $this->assertFalse($navigation->allows($auditor, 'app-admin'));
        $this->assertFalse($navigation->allows($auditor, 'system-admin'));
        $this->assertFalse($navigation->allows($auditor, 'analyst'));
    }

    #[Test]
    public function a_plain_viewer_does_not_reach_compliance(): void
    {
        $this->assertFalse(app(Navigation::class)->allows($this->personOn(Role::Viewer), 'compliance'));
    }

    #[Test]
    public function the_tiers_rank_in_the_documented_order(): void
    {
        $order = array_map(fn (Role $r): string => $r->value, Role::cases());

        $this->assertSame(
            ['system_admin', 'admin', 'domain_owner', 'analyst', 'contributor', 'viewer'],
            $order,
        );

        $this->assertTrue(Role::SystemAdmin->atLeast(Role::Admin));
        $this->assertTrue(Role::DomainOwner->atLeast(Role::Analyst));
        $this->assertFalse(Role::Analyst->atLeast(Role::DomainOwner));
        $this->assertSame(Role::Viewer, Role::default());
    }

    /* -- Route enforcement, which is the layer that actually matters ------ */

    #[Test]
    public function a_business_user_is_denied_an_admin_route_by_url(): void
    {
        // A named Phase 00 acceptance criterion. The rail already hides the
        // link; this proves typing the address does not get past it.
        $this->actingAs($this->personOn(Role::Analyst))
            ->get('/admin')
            ->assertForbidden();
    }

    #[Test]
    public function an_administrator_is_denied_the_system_administration_route(): void
    {
        // The separation the fourth cluster exists for: an administrator who can
        // invite a colleague does not thereby hold every provider credential.
        $this->actingAs($this->personOn(Role::Admin))
            ->get('/admin')
            ->assertForbidden();
    }

    #[Test]
    public function a_system_administrator_reaches_it(): void
    {
        $this->actingAs($this->personOn(Role::SystemAdmin))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Platform Overview');
    }

    #[Test]
    public function a_guest_is_sent_to_sign_in_rather_than_forbidden(): void
    {
        $this->get('/admin')->assertRedirect('/sign-in');
    }

    #[Test]
    public function the_route_policy_and_the_rail_agree(): void
    {
        $navigation = app(Navigation::class);

        foreach ([Role::Viewer, Role::Contributor, Role::Analyst, Role::DomainOwner, Role::Admin, Role::SystemAdmin] as $role) {
            $person = $this->personOn($role);
            $seesInRail = array_key_exists('System Administration', $navigation->for($person));
            $reachesRoute = $this->actingAs($person)->get('/admin')->status() === 200;

            // Two implementations of one rule drift, and the drift is invisible
            // until the looser one is guarding something that matters.
            $this->assertSame(
                $seesInRail,
                $reachesRoute,
                $role->label().' sees and reaches the admin area differently',
            );
        }
    }
}
