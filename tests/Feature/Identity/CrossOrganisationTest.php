<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Identity\Models\AccessReview;
use App\Modules\Identity\Models\BusinessUnit;
use App\Modules\Identity\Models\Organisation;
use App\Modules\Identity\Models\Team;
use App\Modules\Identity\Policies\SystemAdministratorGuard;
use App\Modules\Identity\Services\AccessReviewService;
use App\Modules\Identity\Services\StructureRegistry;
use App\Modules\Identity\Services\UserRegistry;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The organisation boundary, applied to everything gate 2 added.
 *
 * Gate 1 proved the mechanism. This proves each new table is actually behind it,
 * which is the part that gets forgotten: a table that carries
 * `organisation_id` and forgets the trait looks correct in every code review
 * and leaks in production.
 */
class CrossOrganisationTest extends TestCase
{
    use RefreshDatabase;

    private function context(): OrganisationContext
    {
        return app(OrganisationContext::class);
    }

    private function secondOrganisation(): Organisation
    {
        return Organisation::query()->forceCreate([
            'code' => 'SECOND', 'name' => 'Second Customer', 'status' => 'active', 'version' => 1,
        ]);
    }

    private function admin(): User
    {
        $user = User::query()->create(['name' => 'Ada', 'email' => uniqid().'@example.test']);
        $user->forceFill(['role' => Role::SystemAdmin, 'organisation_id' => $this->context()->currentId()])->save();

        return $user->refresh();
    }

    #[Test]
    public function structure_records_are_invisible_across_organisations(): void
    {
        $admin = $this->admin();
        $first = $this->context()->require();

        app(StructureRegistry::class)->createBusinessUnit(['code' => 'fin', 'name' => 'Finance'], $admin);

        $this->assertSame(1, BusinessUnit::query()->count());

        $second = $this->secondOrganisation();
        $this->context()->forget();
        $this->context()->bind($second);

        // The other customer's unit is not merely filtered out of a list - it
        // does not exist as far as this organisation is concerned.
        $this->assertSame(0, BusinessUnit::query()->count());
        $this->assertNull(BusinessUnit::query()->where('code', 'FIN')->first());
    }

    #[Test]
    public function teams_are_invisible_across_organisations(): void
    {
        $admin = $this->admin();
        $unit = app(StructureRegistry::class)->createBusinessUnit(['code' => 'fin', 'name' => 'Finance'], $admin);
        app(StructureRegistry::class)->createTeam(['code' => 'ap', 'name' => 'Accounts Payable', 'business_unit_id' => $unit->getKey()], $admin);

        $this->assertSame(1, Team::query()->count());

        $this->context()->forget();
        $this->context()->bind($this->secondOrganisation());

        $this->assertSame(0, Team::query()->count());
    }

    #[Test]
    public function access_reviews_are_invisible_across_organisations(): void
    {
        // The most sensitive of the three: a review is a list of who can read
        // what, which is exactly the document one customer must never see about
        // another.
        $admin = $this->admin();
        app(AccessReviewService::class)->create('Q3 review', null, null, $admin);

        $this->assertSame(1, AccessReview::query()->count());

        $this->context()->forget();
        $this->context()->bind($this->secondOrganisation());

        $this->assertSame(0, AccessReview::query()->count());
    }

    #[Test]
    public function the_user_registry_is_scoped_even_though_users_carries_no_global_scope(): void
    {
        // `users` is the authentication table, so a fail-closed GLOBAL scope
        // there would turn "no context resolved" into "nobody can sign in,
        // including the administrator who would fix it". The boundary is
        // enforced at every place that lists or administers accounts instead -
        // SEC-DEC-022 - and this is that claim under test.
        $this->admin();
        $first = $this->context()->require();

        $second = $this->secondOrganisation();
        $theirs = User::query()->create(['name' => 'Theirs', 'email' => 'theirs@example.test']);
        $theirs->forceFill(['role' => Role::Admin, 'organisation_id' => $second->getKey()])->save();

        $emails = app(UserRegistry::class)->query()->pluck('email')->all();

        $this->assertNotContains('theirs@example.test', $emails);
    }

    #[Test]
    public function the_last_administrator_count_does_not_borrow_another_organisations(): void
    {
        // Counting another customer's administrator would be the same class of
        // failure as counting a disabled one: a count that says the door is
        // held open by somebody who cannot open it.
        $mine = $this->admin();

        $second = $this->secondOrganisation();
        $theirs = User::query()->create(['name' => 'Theirs', 'email' => 'theirs@example.test']);
        $theirs->forceFill(['role' => Role::SystemAdmin, 'organisation_id' => $second->getKey()])->save();

        $this->assertSame(1, app(SystemAdministratorGuard::class)->activeCount());
        $this->assertFalse(app(SystemAdministratorGuard::class)->permits($mine));
    }

    #[Test]
    public function a_scoped_read_still_fails_closed_with_no_context(): void
    {
        $admin = $this->admin();
        app(StructureRegistry::class)->createBusinessUnit(['code' => 'fin', 'name' => 'Finance'], $admin);

        $this->secondOrganisation();
        $this->context()->forget();

        // With two organisations present the context refuses to resolve by
        // itself, and everything scoped returns nothing rather than everything.
        $this->assertNull($this->context()->current());
        $this->assertSame(0, BusinessUnit::query()->count());
        $this->assertSame(0, AccessReview::query()->count());
        $this->assertSame(0, app(UserRegistry::class)->query()->count());
    }

    #[Test]
    public function a_route_cannot_reach_another_organisations_account_by_id(): void
    {
        // Route-model binding resolves by primary key and knows nothing about
        // the boundary, so a guessed id is the obvious way across it.
        $mine = $this->admin();

        $second = $this->secondOrganisation();
        $theirs = User::query()->create(['name' => 'Theirs', 'email' => 'theirs@example.test']);
        $theirs->forceFill(['role' => Role::Viewer, 'organisation_id' => $second->getKey()])->save();

        $this->actingAs($mine)
            ->get(route('admin.users.show', $theirs))
            ->assertNotFound();
    }
}
