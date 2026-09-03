<?php

declare(strict_types=1);

namespace Tests\Feature\Domains;

use App\Modules\Domains\Models\AccessExpectation;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainKind;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Domains\Models\DomainStatus;
use App\Modules\Domains\Services\BaselineDomainInitialiser;
use App\Modules\Domains\Support\BaselineDomains;
use App\Modules\Organisation\Services\OrganisationService;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DomainFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * How the seven baseline domains arrive - D-46.
 *
 * Two paths, both explicit: the Company Profile integration for a new
 * organisation, and the one-time command for the deployment that already
 * exists and will never run that path again.
 *
 * And two things that must NEVER create a domain: a migration, and a GET.
 */
final class BaselineInitialisationTest extends TestCase
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
     * N42. THE BASELINE SET IS EXACTLY SEVEN, AND CLOSED.
     *
     * "Custom Domains" appears in the source scope beside the seven, and it
     * names the CAPABILITY to add your own - D-44. Reading it as an eighth
     * baseline entry would seed a meaningless record into every deployment, and
     * that is the mutation.
     *
     * Mutation: add 'custom' => 'Custom' to the catalogue.
     */
    public function test_the_baseline_catalogue_is_exactly_seven_named_domains(): void
    {
        $this->assertSame(
            ['executive', 'sales', 'finance', 'people', 'operations', 'customer', 'learning'],
            BaselineDomains::codes(),
            'The baseline set is not the approved seven.'
        );

        $this->assertCount(7, BaselineDomains::CATALOGUE);

        $this->assertArrayNotHasKey(
            'custom',
            BaselineDomains::CATALOGUE,
            '"Custom Domains" is the capability to add your own, not an eighth baseline domain (D-44).'
        );
    }

    /**
     * N39. The seven arrive DISABLED, UNOWNED and "Not yet determined".
     *
     * SemantIQ knowing the vocabulary is not the organisation using it. Arriving
     * enabled would make "Enabled" mean nothing on the first screen anybody
     * sees, and would make the D-42 enable rule incoherent.
     *
     * Mutation: create them enabled; or owned by the creating administrator.
     */
    public function test_baseline_domains_arrive_disabled_unowned_and_undecided(): void
    {
        $organisation = $this->make->organisation();
        $creator = $this->make->user($organisation, administrator: true);

        app(BaselineDomainInitialiser::class)->initialise($organisation, $creator);

        $domains = BusinessDomain::query()->get();

        $this->assertCount(7, $domains);

        foreach ($domains as $domain) {
            $this->assertSame(DomainKind::Baseline, $domain->kind, "[{$domain->code}] is not baseline.");
            $this->assertSame(DomainStatus::Disabled, $domain->status, "[{$domain->code}] arrived enabled.");
            $this->assertSame(AccessExpectation::Undecided, $domain->access_expectation);
            $this->assertNull($domain->description);
            $this->assertSame($organisation->id, $domain->organisation_id);
        }

        $this->assertSame(
            0,
            DomainOwnership::query()->count(),
            'Initialisation made somebody accountable for a domain nobody has decided to use.'
        );
    }

    /**
     * N37. Running it twice produces seven domains, not fourteen.
     *
     * Mutation: drop the existence check; key it on name rather than code.
     */
    public function test_initialisation_is_idempotent(): void
    {
        $organisation = $this->make->organisation();

        $first = app(BaselineDomainInitialiser::class)->initialise($organisation);
        $second = app(BaselineDomainInitialiser::class)->initialise($organisation);
        $third = app(BaselineDomainInitialiser::class)->initialise($organisation);

        $this->assertCount(7, $first, 'The first run did not create the seven.');
        $this->assertSame([], $second, 'The second run created something.');
        $this->assertSame([], $third);

        $this->assertSame(7, BusinessDomain::query()->count());
    }

    /**
     * N38. IT IS NOT A RESET.
     *
     * Run after an administrator has renamed, enabled and assigned an owner to
     * a domain, it changes none of that. A re-run that quietly restored the
     * standard name would undo a decision the organisation made.
     *
     * Mutation: have it update name or status on a row that already exists.
     */
    public function test_running_it_again_changes_nothing_an_administrator_has_decided(): void
    {
        $organisation = $this->make->organisation();
        $owner = $this->make->user($organisation);

        app(BaselineDomainInitialiser::class)->initialise($organisation);

        $sales = BusinessDomain::query()->where('code', 'sales')->sole();

        $sales->forceFill([
            'name' => 'Commercial',
            'description' => 'Everything we sell',
            'status' => DomainStatus::Enabled->value,
            'access_expectation' => AccessExpectation::Limited->value,
        ])->save();

        $this->domains->ownership($sales, $owner);

        $created = app(BaselineDomainInitialiser::class)->initialise($organisation);

        $this->assertSame([], $created);

        $sales->refresh();

        $this->assertSame('Commercial', $sales->name, 'A re-run reset the display name.');
        $this->assertSame('Everything we sell', $sales->description);
        $this->assertSame(DomainStatus::Enabled, $sales->status, 'A re-run disabled a domain in use.');
        $this->assertSame(AccessExpectation::Limited, $sales->access_expectation);
        $this->assertSame(1, DomainOwnership::query()->count(), 'A re-run touched ownership.');
    }

    /**
     * N40. NO MIGRATION WRITES A business_domains ROW.
     *
     * Structure is a migration's job. A migration also runs before any
     * organisation exists, so it could not name one even if seeding there were
     * acceptable.
     *
     * Mutation: move initialisation into a migration.
     */
    public function test_no_migration_creates_a_domain_row(): void
    {
        $scanned = 0;

        foreach (glob(base_path('database/migrations/*.php')) ?: [] as $file) {
            $source = (string) file_get_contents($file);
            $scanned++;

            foreach (['business_domains', 'business_domain_owners'] as $table) {
                foreach (["DB::table('{$table}')", 'insert('] as $write) {
                    if (str_contains($source, "DB::table('{$table}')")) {
                        $this->fail(basename($file).' writes rows into '.$table.'.');
                    }
                }
            }

            $this->assertStringNotContainsString(
                'BaselineDomainInitialiser',
                $source,
                basename($file).' initialises business domains. That belongs to an explicit, '
                .'idempotent path that runs when an organisation exists to name.'
            );
        }

        $this->assertGreaterThan(10, $scanned, 'Almost no migrations were scanned.');
    }

    /**
     * N41. NO GET REQUEST CREATES A DOMAIN.
     *
     * "Materialise on first view" looks convenient and quietly turns a read
     * path into a write path that races itself under two administrators.
     * Opening the screen with an empty table must leave it empty.
     *
     * Mutation: have DomainController::index() initialise when the list is empty.
     */
    public function test_opening_the_screen_creates_nothing(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->assertSame(0, BusinessDomain::query()->count());

        $response = $this->actingAsUser($admin)->get('/console/domains');

        $response->assertOk();

        $this->assertSame(
            0,
            BusinessDomain::query()->count(),
            'Loading the Business Domains screen created domains. A read path must not write.'
        );

        $this->assertFalse($response->viewData('page')['props']['anyDomains']);
    }

    /**
     * The Company Profile integration - the path a NEW organisation takes.
     *
     * In the same transaction as the profile, for the same reason D-16's
     * association is: an organisation without its baseline vocabulary would
     * need a repair path this unit deliberately does not have.
     */
    public function test_creating_the_company_profile_materialises_the_seven(): void
    {
        $creator = $this->make->user();

        $organisation = app(OrganisationService::class)->createProfile(
            ['name' => 'Acme', 'legal_name' => null, 'country' => null, 'industry' => null],
            $creator
        );

        $this->assertSame(7, BusinessDomain::query()->where('organisation_id', $organisation->id)->count());

        // Ordered by id: they are created in catalogue order, and without an
        // ORDER BY the engine may return them in any order at all.
        $this->assertSame(
            BaselineDomains::codes(),
            BusinessDomain::query()
                ->where('organisation_id', $organisation->id)
                ->orderBy('id')
                ->pluck('code')
                ->all()
        );

        $this->assertSame(0, DomainOwnership::query()->count());
    }

    /**
     * N43. The command refuses when there is no organisation, and creates
     * nothing.
     *
     * Mutation: let it write with a null organisation_id.
     */
    public function test_the_command_refuses_without_an_organisation(): void
    {
        $this->artisan('domains:initialise')
            ->expectsOutputToContain('There is no organisation yet.')
            ->assertFailed();

        $this->assertSame(0, BusinessDomain::query()->count());
    }

    /**
     * The command reports what it actually did, both times.
     *
     * A command that prints nothing cannot be verified, and its output is the
     * evidence recorded in the verification document.
     */
    public function test_the_command_reports_what_it_created_and_what_was_already_there(): void
    {
        $organisation = $this->make->organisation();

        $this->artisan('domains:initialise')
            ->expectsOutputToContain('THIS COMMAND WRITES')
            ->expectsOutputToContain('executive, sales, finance, people, operations, customer, learning')
            ->assertSuccessful();

        $this->assertSame(7, BusinessDomain::query()->count());

        $this->artisan('domains:initialise')
            ->expectsOutputToContain('all seven were already present')
            ->assertSuccessful();

        $this->assertSame(7, BusinessDomain::query()->count());
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
