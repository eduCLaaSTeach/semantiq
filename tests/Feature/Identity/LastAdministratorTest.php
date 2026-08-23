<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Identity\Policies\SystemAdministratorGuard;
use App\Modules\Identity\Services\UserRegistry;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * VAL-USER-LASTADMIN-001. The invariant Release 1 gate 2 exists for.
 *
 * There are three ways to empty the System Administrator role - delete the
 * account, suspend it, lower its tier - reached from three different screens,
 * and a fourth will be added by some feature nobody has thought of yet. Every
 * one of them is tested here separately, because a check that only holds on the
 * path somebody remembered is not an invariant.
 *
 * The failure this guards against is total and unrecoverable from inside the
 * application: an instance with no active administrator has nobody able to
 * appoint one, including nobody able to undo whatever caused it.
 */
class LastAdministratorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An account placed in the organisation currently in force.
     *
     * Placement is not incidental to these tests. `UserRegistry` refuses any
     * mutation on a subject outside the current organisation
     * (VAL-ORG-SUBJECT-001), so an unplaced account is unmanageable - which is
     * exactly what a real account looks like, because both the registry and
     * Microsoft sign-in place one at creation. A helper that skipped it would
     * be testing a state the application cannot produce.
     */
    private function person(Role $role, LifecycleStatus $status = LifecycleStatus::Active): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);
        $user->forceFill([
            'role' => $role,
            'status' => $status,
            'organisation_id' => app(OrganisationContext::class)->currentId(),
        ])->save();

        return $user->refresh();
    }

    private function registry(): UserRegistry
    {
        return app(UserRegistry::class);
    }

    #[Test]
    public function the_last_active_administrator_cannot_be_demoted(): void
    {
        $last = $this->person(Role::SystemAdmin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('last active System Administrator');

        $this->registry()->changeTier($last, Role::Admin, $last);
    }

    #[Test]
    public function the_last_active_administrator_cannot_be_disabled(): void
    {
        $last = $this->person(Role::SystemAdmin);

        $this->expectException(RuntimeException::class);

        $this->registry()->changeStatus($last, LifecycleStatus::Disabled, $last);
    }

    #[Test]
    public function the_last_active_administrator_cannot_be_locked(): void
    {
        // Locking is not disabling, and a check written only against `disabled`
        // would let this one through. Any state that stops them signing in
        // empties the role just as completely.
        $last = $this->person(Role::SystemAdmin);

        $this->expectException(RuntimeException::class);

        $this->registry()->changeStatus($last, LifecycleStatus::Locked, $last);
    }

    #[Test]
    public function a_second_active_administrator_makes_the_first_removable(): void
    {
        $first = $this->person(Role::SystemAdmin);
        $this->person(Role::SystemAdmin);

        $this->registry()->changeStatus($first, LifecycleStatus::Disabled, $first);

        $this->assertSame(LifecycleStatus::Disabled, $first->refresh()->status);
    }

    #[Test]
    public function a_disabled_administrator_does_not_count_as_holding_the_door_open(): void
    {
        $active = $this->person(Role::SystemAdmin);
        $this->person(Role::SystemAdmin, LifecycleStatus::Disabled);

        // The failure this prevents: a count that says two administrators exist
        // while only one of them can actually sign in, so removing that one
        // locks everybody out while the check reports everything is fine.
        $this->assertSame(1, app(SystemAdministratorGuard::class)->activeCount());

        $this->expectException(RuntimeException::class);
        $this->registry()->changeStatus($active, LifecycleStatus::Disabled, $active);
    }

    #[Test]
    public function an_administrator_whose_access_window_has_closed_does_not_count(): void
    {
        $active = $this->person(Role::SystemAdmin);

        // Expired is a status, and the guard counts on status, so this is the
        // same protection reached through the access window rather than a
        // deliberate suspension.
        $this->person(Role::SystemAdmin, LifecycleStatus::Expired);

        $this->assertSame(1, app(SystemAdministratorGuard::class)->activeCount());

        $this->expectException(RuntimeException::class);
        $this->registry()->changeStatus($active, LifecycleStatus::Disabled, $active);
    }

    #[Test]
    public function promoting_the_last_administrator_is_not_blocked(): void
    {
        // The guard must not be so broad that it freezes the account entirely.
        // Nothing about raising a tier can empty the role.
        $last = $this->person(Role::SystemAdmin);

        $this->registry()->changeTier($last, Role::SystemAdmin, $last);

        $this->assertSame(Role::SystemAdmin, $last->refresh()->role);
    }

    #[Test]
    public function removing_a_non_administrator_is_never_blocked(): void
    {
        $admin = $this->person(Role::SystemAdmin);
        $viewer = $this->person(Role::Viewer);

        $this->registry()->changeStatus($viewer, LifecycleStatus::Disabled, $admin);

        $this->assertSame(LifecycleStatus::Disabled, $viewer->refresh()->status);
    }

    #[Test]
    public function the_screen_is_told_before_it_offers_a_control_that_would_fail(): void
    {
        $last = $this->person(Role::SystemAdmin);
        $guard = app(SystemAdministratorGuard::class);

        // The screen asks so the control is absent rather than present and
        // fatal. The service asks again, because a hidden button is not an
        // authorization control - which the throwing tests above prove.
        $this->assertFalse($guard->permits($last));

        $this->person(Role::SystemAdmin);

        $this->assertTrue($guard->permits($last));
    }

    #[Test]
    public function the_route_refuses_the_change_and_shows_the_reason(): void
    {
        $last = $this->person(Role::SystemAdmin);

        $this->actingAs($last)
            ->from(route('admin.users.show', $last))
            ->post(route('admin.users.status', $last), ['status' => 'disabled'])
            ->assertRedirect(route('admin.users.show', $last))
            ->assertSessionHasErrors('authority');

        $this->assertSame(LifecycleStatus::Active, $last->refresh()->status);
    }
}
