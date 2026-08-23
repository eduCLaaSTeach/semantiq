<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Enums\UserType;
use App\Modules\Identity\Services\UserRegistry;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The user registry. Feature ADM-005.
 *
 * Account lifecycle, uniqueness, the access window, and the rule that every one
 * of those changes leaves a trail.
 */
class UserRegistryTest extends TestCase
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
    public function a_new_account_starts_invited_rather_than_active(): void
    {
        $admin = $this->person(Role::SystemAdmin);

        $created = $this->registry()->create([
            'name' => 'New Person',
            'email' => 'new@example.test',
            'user_type' => UserType::Internal,
            'role' => Role::Viewer,
        ], $admin);

        // An account nobody has ever signed into is not the same as one in use,
        // and starting active would make the two indistinguishable in every
        // later review.
        $this->assertSame(LifecycleStatus::Invited, $created->status);
        $this->assertFalse($created->mayAuthenticate());
    }

    #[Test]
    public function an_email_can_only_be_used_once(): void
    {
        // VAL-USER-EMAIL-001.
        $admin = $this->person(Role::SystemAdmin);

        $this->registry()->create([
            'name' => 'First', 'email' => 'shared@example.test',
            'user_type' => UserType::Internal, 'role' => Role::Viewer,
        ], $admin);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already exists');

        $this->registry()->create([
            'name' => 'Second', 'email' => 'shared@example.test',
            'user_type' => UserType::Internal, 'role' => Role::Viewer,
        ], $admin);
    }

    #[Test]
    public function a_disabled_account_cannot_authenticate(): void
    {
        // VAL-USER-DISABLED-001, asked of the model rather than of a screen,
        // because both sign-in paths and the authorization layer all rely on
        // this one answer.
        foreach ([LifecycleStatus::Disabled, LifecycleStatus::Locked, LifecycleStatus::Expired, LifecycleStatus::Invited] as $status) {
            $this->assertFalse(
                $this->person(Role::Admin, $status)->mayAuthenticate(),
                $status->value.' should not be able to sign in',
            );
        }

        $this->assertTrue($this->person(Role::Admin)->mayAuthenticate());
    }

    #[Test]
    public function an_access_window_closes_by_itself(): void
    {
        // VAL-USER-WINDOW-001. A contractor's access ending on a date is a
        // promise the system keeps without anybody remembering to.
        $person = $this->person(Role::Analyst);
        $person->forceFill(['access_end' => now()->subDay()])->save();

        $this->assertFalse($person->refresh()->mayAuthenticate());
        $this->assertTrue($person->accessWindowHasClosed());

        $future = $this->person(Role::Analyst);
        $future->forceFill(['access_start' => now()->addWeek()])->save();

        // And it does not open early either.
        $this->assertFalse($future->refresh()->mayAuthenticate());
    }

    #[Test]
    public function a_closed_window_moves_an_active_account_to_expired(): void
    {
        $admin = $this->person(Role::SystemAdmin);
        $person = $this->person(Role::Analyst);

        $this->registry()->update($person, [
            'name' => 'Test Person',
            'user_type' => UserType::External,
            'access_end' => now()->subDay()->toDateString(),
        ], $admin);

        // Otherwise the account reads active while being unable to sign in -
        // two sources of truth about the same thing.
        $this->assertSame(LifecycleStatus::Expired, $person->refresh()->status);
    }

    #[Test]
    public function a_disabled_account_is_refused_at_the_sign_in_form(): void
    {
        $person = User::query()->create([
            'name' => 'Blocked', 'email' => 'blocked@example.test', 'password' => 'correct-horse-battery',
        ]);
        $person->forceFill([
            'role' => Role::Analyst,
            'status' => LifecycleStatus::Disabled,
            'password' => Hash::make('correct-horse-battery'),
        ])->save();

        $this->post('/sign-in', ['email' => 'blocked@example.test', 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors('form');

        $this->assertGuest();
    }

    #[Test]
    public function the_refusal_never_says_the_account_is_disabled(): void
    {
        $person = User::query()->create([
            'name' => 'Blocked', 'email' => 'blocked@example.test', 'password' => 'correct-horse-battery',
        ]);
        $person->forceFill([
            'role' => Role::Analyst,
            'status' => LifecycleStatus::Disabled,
            'password' => Hash::make('correct-horse-battery'),
        ])->save();

        // The one message, asserted literally against BOTH cases. Saying "this
        // account is disabled" would turn the form into a directory lookup that
        // confirms who works here and what happened to them.
        $expected = 'Those credentials do not match our records.';

        $this->post('/sign-in', ['email' => 'blocked@example.test', 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors(['form' => $expected]);

        $this->flushSession();

        $this->post('/sign-in', ['email' => 'blocked@example.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['form' => $expected]);

        $this->flushSession();

        // And an address that belongs to nobody gets the same sentence again.
        $this->post('/sign-in', ['email' => 'nobody@example.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['form' => $expected]);

        $recorded = AuditEvent::withoutOrganisationScope()
            ->where('action', 'auth.login.failed')
            ->where('outcome', 'denied')
            ->first();

        $this->assertNotNull($recorded);
        $this->assertStringContainsString('Disabled', (string) $recorded->reason);
    }

    #[Test]
    public function every_access_change_leaves_a_trail(): void
    {
        $admin = $this->person(Role::SystemAdmin);
        $second = $this->person(Role::SystemAdmin);
        $subject = $this->person(Role::Viewer);

        $this->actingAs($admin);

        $this->registry()->changeTier($subject, Role::Analyst, $admin);
        $this->registry()->changeStatus($subject, LifecycleStatus::Disabled, $admin);
        $this->registry()->changeStatus($subject, LifecycleStatus::Active, $admin);
        $this->registry()->grantEntitlement($subject, BusinessDomain::Sales, $admin);
        $this->registry()->revokeEntitlement($subject, BusinessDomain::Sales, $admin);

        $actions = AuditEvent::withoutOrganisationScope()->pluck('action')->all();

        foreach ([
            'user.role.assigned',
            'user.disabled',
            'user.unlocked',
            'user.entitlement.granted',
            'user.entitlement.revoked',
        ] as $expected) {
            $this->assertContains($expected, $actions, $expected.' was not audited');
        }
    }

    #[Test]
    public function a_tier_change_and_a_domain_grant_are_different_events(): void
    {
        // The trail must never blur "made them an administrator" with "gave
        // them Finance". They are different decisions with different
        // consequences, and an investigation has to be able to tell them apart.
        $admin = $this->person(Role::SystemAdmin);
        $this->person(Role::SystemAdmin);
        $subject = $this->person(Role::Viewer);

        $this->actingAs($admin);

        $this->registry()->changeTier($subject, Role::Admin, $admin);
        $this->registry()->grantEntitlement($subject, BusinessDomain::Finance, $admin);

        $tier = AuditEvent::withoutOrganisationScope()->where('action', 'user.role.assigned')->firstOrFail();
        $domain = AuditEvent::withoutOrganisationScope()->where('action', 'user.entitlement.granted')->firstOrFail();

        $this->assertNotSame($tier->getKey(), $domain->getKey());
        $this->assertSame(['role' => 'admin'], $tier->after_summary);
        $this->assertSame('finance', $domain->after_summary['domain']);
    }

    #[Test]
    public function no_password_or_token_reaches_a_user_audit_summary(): void
    {
        $admin = $this->person(Role::SystemAdmin);
        $this->actingAs($admin);

        $created = $this->registry()->create([
            'name' => 'New Person',
            'email' => 'new@example.test',
            'user_type' => UserType::Internal,
            'role' => Role::Viewer,
        ], $admin);

        $created->forceFill(['password' => Hash::make('a-real-password')])->save();

        $event = AuditEvent::withoutOrganisationScope()->where('action', 'user.created')->firstOrFail();
        $encoded = json_encode($event->toArray()) ?: '';

        $this->assertStringNotContainsString('password', strtolower($encoded));
        $this->assertStringNotContainsString('a-real-password', $encoded);
    }
}
