<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The organisation boundary, proven rather than asserted.
 *
 * The phase checklist requires evidence that a request cannot reach records
 * outside its active organisation, and the sovereignty standard lists a
 * tenant-isolation failure as a release blocker. These tests use two
 * organisation fixtures deliberately, which the checklist permits even though
 * the current deployment is single-customer: an isolation test that only ever
 * sees one tenant proves nothing.
 *
 * `users` is the subject because it is the only customer-owned table that
 * exists so far. Every table added later carries the same trait and inherits
 * the same guarantee.
 */
class OrganisationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $alpha;

    private Organisation $beta;

    private User $alphaUser;

    private User $betaUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Organisation::factory()->create(['name' => 'Alpha Group']);
        $this->beta = Organisation::factory()->create(['name' => 'Beta Holdings']);

        $this->alphaUser = $this->userIn($this->alpha, 'alpha@example.test');
        $this->betaUser = $this->userIn($this->beta, 'beta@example.test');
    }

    private function userIn(Organisation $organisation, string $email): User
    {
        $user = User::query()->create([
            'name' => 'Person '.$organisation->name,
            'email' => $email,
            'password' => null,
        ]);

        $user->forceFill([
            'organisation_id' => $organisation->id,
            'role' => Role::SystemAdmin,
        ])->save();

        return $user->refresh();
    }

    #[Test]
    public function a_signed_in_person_sees_only_their_own_organisations_records(): void
    {
        $this->actingAs($this->alphaUser);

        $visible = User::query()->pluck('email')->all();

        $this->assertSame(['alpha@example.test'], $visible);
        $this->assertNotContains('beta@example.test', $visible);
    }

    #[Test]
    public function a_record_from_another_organisation_cannot_be_read_by_id(): void
    {
        $this->actingAs($this->alphaUser);

        // Even knowing the exact key, the row is not reachable.
        $this->assertNull(User::query()->find($this->betaUser->id));
    }

    #[Test]
    public function a_record_from_another_organisation_cannot_be_updated(): void
    {
        $this->actingAs($this->alphaUser);

        $affected = User::query()->where('id', $this->betaUser->id)->update(['name' => 'Overwritten']);

        $this->assertSame(0, $affected);

        // Confirmed from outside the scope: the row is genuinely untouched.
        $this->assertSame(
            'Person Beta Holdings',
            app(OrganisationContext::class)->withoutScoping(
                fn () => User::query()->find($this->betaUser->id)->name
            )
        );
    }

    #[Test]
    public function a_record_from_another_organisation_cannot_be_deleted(): void
    {
        $this->actingAs($this->alphaUser);

        $deleted = User::query()->where('id', $this->betaUser->id)->delete();

        $this->assertSame(0, $deleted);

        $this->assertTrue(
            app(OrganisationContext::class)->withoutScoping(
                fn () => User::query()->whereKey($this->betaUser->id)->exists()
            )
        );
    }

    #[Test]
    public function the_scope_fails_closed_when_there_is_no_organisation_context(): void
    {
        // Nobody signed in, no organisation bound: the safest answer is nothing.
        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function a_user_with_no_organisation_reaches_no_records(): void
    {
        $orphan = User::query()->create([
            'name' => 'Unassigned', 'email' => 'orphan@example.test', 'password' => null,
        ]);
        $orphan->forceFill(['organisation_id' => null, 'role' => Role::SystemAdmin])->save();

        $this->actingAs($orphan->refresh());

        // A null organisation is treated as no access, never as unrestricted access.
        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function a_new_record_is_stamped_with_the_active_organisation(): void
    {
        $this->actingAs($this->alphaUser);

        $created = User::query()->create([
            'name' => 'New Joiner', 'email' => 'joiner@example.test', 'password' => null,
        ]);

        $this->assertSame($this->alpha->id, $created->organisation_id);
    }

    #[Test]
    public function an_explicitly_bound_organisation_scopes_work_outside_a_request(): void
    {
        // A queued job has no session, so it binds the organisation itself.
        app(OrganisationContext::class)->set($this->beta);

        $visible = User::query()->pluck('email')->all();

        $this->assertSame(['beta@example.test'], $visible);
    }

    #[Test]
    public function scoping_can_be_lifted_deliberately_for_system_work(): void
    {
        $this->actingAs($this->alphaUser);

        $all = app(OrganisationContext::class)->withoutScoping(
            fn () => User::query()->pluck('email')->all()
        );

        sort($all);
        $this->assertSame(['alpha@example.test', 'beta@example.test'], $all);
    }

    #[Test]
    public function lifting_the_scope_is_restored_even_when_the_callback_throws(): void
    {
        $this->actingAs($this->alphaUser);

        try {
            app(OrganisationContext::class)->withoutScoping(function (): void {
                throw new \RuntimeException('deliberate');
            });
        } catch (\RuntimeException) {
            // Expected.
        }

        // An exception must not leave the process running unscoped.
        $this->assertFalse(app(OrganisationContext::class)->isScopingDisabled());
        $this->assertSame(['alpha@example.test'], User::query()->pluck('email')->all());
    }

    #[Test]
    public function the_organisation_relationship_resolves(): void
    {
        $this->assertSame('Alpha Group', $this->alphaUser->organisation->name);
        $this->assertTrue($this->alpha->isActive());
        $this->assertFalse(Organisation::factory()->suspended()->create()->isActive());
    }

    #[Test]
    public function every_organisation_receives_a_stable_external_identifier(): void
    {
        $this->assertNotEmpty($this->alpha->organisation_uid);
        $this->assertNotSame($this->alpha->organisation_uid, $this->beta->organisation_uid);
    }
}
