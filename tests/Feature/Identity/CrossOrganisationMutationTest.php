<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Models\DomainEntitlement;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Enums\UserType;
use App\Modules\Identity\Exceptions\SubjectOutsideOrganisation;
use App\Modules\Identity\Models\AccessRole;
use App\Modules\Identity\Models\Organisation;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Services\UserRegistry;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The tenancy boundary on every MUTATION of an existing account.
 *
 * This file exists because the boundary was missing and a test written to
 * demonstrate it succeeded: a System Administrator in one organisation could
 * disable, demote, re-role and re-entitle an account in another simply by
 * supplying its id. Read paths were protected; the five write routes were not,
 * and neither was `UserRegistry` itself.
 *
 * `users` deliberately carries no global organisation scope - it is the
 * authentication table, and a fail-closed global scope there would mean nobody
 * can sign in when the context fails to resolve (SEC-DEC-022). That choice puts
 * the whole burden on the write paths, so every one of them is tested here.
 *
 * THE ATTACKER IN EVERY TEST IS A SYSTEM ADMINISTRATOR with a second
 * administrator beside them. That matters: it means a refusal cannot be
 * explained away by an insufficient tier, a missing permission, or the
 * last-administrator invariant. The ONLY thing left to refuse the operation is
 * organisation isolation, which is precisely the claim under test.
 */
class CrossOrganisationMutationTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $other;

    private User $attacker;

    private User $victim;

    protected function setUp(): void
    {
        parent::setUp();

        $ours = app(OrganisationContext::class)->require();

        $this->other = Organisation::query()->forceCreate([
            'code' => 'OTHER', 'name' => 'Other Customer', 'status' => 'active', 'version' => 1,
        ]);

        /*
         * The context is bound explicitly, because a second organisation now
         * exists and automatic resolution deliberately stops in that case. The
         * attacker is acting as OUR organisation throughout.
         */
        app(OrganisationContext::class)->forget();
        app(OrganisationContext::class)->bind($ours);

        $this->attacker = $this->accountIn($ours->getKey(), 'attacker@example.test', Role::SystemAdmin);

        /* A spare administrator, so the last-administrator invariant is never
         * the thing doing the refusing. */
        $this->accountIn($ours->getKey(), 'spare@example.test', Role::SystemAdmin);

        /* The victim is a plain Viewer in the OTHER organisation: nothing about
         * their own authority can explain a refusal either. */
        $this->victim = $this->accountIn($this->other->getKey(), 'victim@example.test', Role::Viewer);
    }

    private function accountIn(int $organisationId, string $email, Role $role): User
    {
        $user = User::query()->create(['name' => 'Person', 'email' => $email]);
        $user->forceFill([
            'role' => $role,
            'status' => LifecycleStatus::Active,
            'organisation_id' => $organisationId,
        ])->save();

        return $user->refresh();
    }

    private function registry(): UserRegistry
    {
        return app(UserRegistry::class);
    }

    /**
     * Everything about the victim that must be unchanged after a refusal.
     *
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $victim = $this->victim->fresh();

        return [
            'name' => $victim->name,
            'email' => $victim->email,
            'role' => $victim->role->value,
            'status' => $victim->status->value,
            'user_type' => $victim->user_type->value,
            'organisation_id' => $victim->organisation_id,
            'business_unit_id' => $victim->business_unit_id,
            'access_end' => $victim->access_end?->toDateString(),
            'roles' => UserRole::query()->where('user_id', $victim->getKey())->pluck('role_id')->sort()->values()->all(),
            /* pluck() runs the model cast, so these arrive as BusinessDomain
             * instances rather than strings - the same trap that once made an
             * entitlement grid render empty. Normalised here so the snapshot
             * compares by value. */
            'entitlements' => DomainEntitlement::query()
                ->where('user_id', $victim->getKey())
                ->pluck('domain')
                ->map(fn (BusinessDomain|string $d): string => $d instanceof BusinessDomain ? $d->value : $d)
                ->sort()->values()->all(),
        ];
    }

    /**
     * Assert the refusal changed nothing and was recorded.
     *
     * @param  array<string, mixed>  $before
     */
    private function assertRefused(array $before): void
    {
        $this->assertSame($before, $this->snapshot(), 'The cross-organisation subject was modified.');

        $denial = AuditEvent::withoutOrganisationScope()
            ->where('action', 'privileged.action.denied')
            ->where('resource_id', (string) $this->victim->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($denial, 'The refusal left no audit event.');
        $this->assertSame('denied', $denial->outcome->value);
        $this->assertStringContainsString('Cross-organisation', (string) $denial->reason);

        /* The trail must not name the other organisation. A log line saying
         * which customer owns which id is a leak of its own. */
        $this->assertStringNotContainsString('Other Customer', (string) $denial->reason);
    }

    /* ---- Direct service tests. The authoritative boundary. ------------ */

    #[Test]
    public function the_service_refuses_a_cross_organisation_profile_update(): void
    {
        $before = $this->snapshot();

        $this->expectException(SubjectOutsideOrganisation::class);

        try {
            $this->registry()->update($this->victim, [
                'name' => 'Renamed By Attacker',
                'user_type' => UserType::Service,
            ], $this->attacker);
        } finally {
            $this->assertRefused($before);
        }
    }

    #[Test]
    public function the_service_refuses_a_cross_organisation_tier_change(): void
    {
        $before = $this->snapshot();

        $this->expectException(SubjectOutsideOrganisation::class);

        try {
            $this->registry()->changeTier($this->victim, Role::SystemAdmin, $this->attacker);
        } finally {
            $this->assertRefused($before);
        }
    }

    #[Test]
    public function the_service_refuses_a_cross_organisation_status_change(): void
    {
        $before = $this->snapshot();

        $this->expectException(SubjectOutsideOrganisation::class);

        try {
            $this->registry()->changeStatus($this->victim, LifecycleStatus::Disabled, $this->attacker);
        } finally {
            $this->assertRefused($before);
        }
    }

    #[Test]
    public function the_service_refuses_a_cross_organisation_role_assignment(): void
    {
        $before = $this->snapshot();
        $role = AccessRole::query()->where('code', 'admin')->firstOrFail();

        $this->expectException(SubjectOutsideOrganisation::class);

        try {
            $this->registry()->assignRole($this->victim, $role, $this->attacker);
        } finally {
            $this->assertRefused($before);
        }
    }

    #[Test]
    public function the_service_refuses_a_cross_organisation_role_removal(): void
    {
        $role = AccessRole::query()->where('code', 'viewer')->firstOrFail();

        /* Given directly, not through the service, so the removal has something
         * real to fail to remove. */
        $this->victim->accessRoles()->attach($role->getKey());

        $before = $this->snapshot();
        $this->assertNotSame([], $before['roles']);

        $this->expectException(SubjectOutsideOrganisation::class);

        try {
            $this->registry()->removeRole($this->victim, $role, $this->attacker);
        } finally {
            $this->assertRefused($before);
        }
    }

    #[Test]
    public function the_service_refuses_a_cross_organisation_entitlement_grant(): void
    {
        $before = $this->snapshot();

        $this->expectException(SubjectOutsideOrganisation::class);

        try {
            $this->registry()->grantEntitlement($this->victim, BusinessDomain::Finance, $this->attacker);
        } finally {
            $this->assertRefused($before);
        }
    }

    #[Test]
    public function the_service_refuses_a_cross_organisation_entitlement_revoke(): void
    {
        DomainEntitlement::query()->create([
            'user_id' => $this->victim->getKey(),
            'domain' => BusinessDomain::People->value,
        ]);

        $before = $this->snapshot();
        $this->assertSame(['people'], $before['entitlements']);

        $this->expectException(SubjectOutsideOrganisation::class);

        try {
            $this->registry()->revokeEntitlement($this->victim, BusinessDomain::People, $this->attacker);
        } finally {
            $this->assertRefused($before);
        }
    }

    #[Test]
    public function the_service_refuses_a_role_belonging_to_another_organisation(): void
    {
        // The same hole from the other side: our own account, their role.
        $ourPerson = $this->accountIn(
            app(OrganisationContext::class)->require()->getKey(),
            'ours@example.test',
            Role::Analyst,
        );

        $theirRole = new AccessRole;
        $theirRole->forceFill([
            'organisation_id' => $this->other->getKey(),
            'code' => 'their_role',
            'name' => 'Their Role',
            'tier' => Role::Analyst->value,
            'is_system' => false,
            'status' => LifecycleStatus::Active->value,
            'version' => 1,
        ])->save();

        $this->expectException(SubjectOutsideOrganisation::class);

        try {
            $this->registry()->assignRole($ourPerson, $theirRole, $this->attacker);
        } finally {
            $this->assertSame(
                0,
                UserRole::query()->where('user_id', $ourPerson->getKey())->count(),
                'A role from another organisation was attached to one of our accounts.',
            );
        }
    }

    #[Test]
    public function an_unplaced_account_is_refused_as_well_as_a_foreign_one(): void
    {
        // Fails closed in both directions. Treating "unknown owner" as "mine"
        // is how a boundary check becomes a boundary hole.
        $orphan = User::query()->create(['name' => 'Orphan', 'email' => 'orphan@example.test']);
        $orphan->forceFill(['role' => Role::Viewer, 'status' => LifecycleStatus::Active])->save();

        $this->expectException(SubjectOutsideOrganisation::class);

        $this->registry()->changeStatus($orphan->refresh(), LifecycleStatus::Disabled, $this->attacker);
    }

    /* ---- HTTP route tests. The early check. --------------------------- */

    #[Test]
    public function every_mutation_route_refuses_a_cross_organisation_subject(): void
    {
        $before = $this->snapshot();
        $role = AccessRole::query()->where('code', 'admin')->firstOrFail();

        $attempts = [
            'profile update' => ['put', route('admin.users.update', $this->victim), [
                'name' => 'Renamed By Attacker', 'user_type' => 'internal',
            ]],
            'tier change' => ['post', route('admin.users.tier', $this->victim), [
                'role' => Role::SystemAdmin->value,
            ]],
            'status change' => ['post', route('admin.users.status', $this->victim), [
                'status' => LifecycleStatus::Disabled->value,
            ]],
            'role assignment' => ['post', route('admin.users.roles', $this->victim), [
                'role_id' => $role->getKey(), 'operation' => 'assign',
            ]],
            'role removal' => ['post', route('admin.users.roles', $this->victim), [
                'role_id' => $role->getKey(), 'operation' => 'remove',
            ]],
            'entitlement grant' => ['post', route('admin.users.entitlements', $this->victim), [
                'domain' => BusinessDomain::Finance->value, 'operation' => 'grant',
            ]],
            'entitlement revoke' => ['post', route('admin.users.entitlements', $this->victim), [
                'domain' => BusinessDomain::Finance->value, 'operation' => 'revoke',
            ]],
        ];

        foreach ($attempts as $label => [$verb, $url, $payload]) {
            $response = $this->actingAs($this->attacker)->$verb($url, $payload);

            /* 404, not 403: a 403 confirms the id exists and belongs to
             * somebody. From this organisation's point of view the record
             * genuinely is not found. */
            $response->assertNotFound();

            $this->assertSame($before, $this->snapshot(), $label.' modified the cross-organisation subject.');
        }
    }

    #[Test]
    public function no_cross_organisation_data_is_returned_by_the_read_paths(): void
    {
        $this->actingAs($this->attacker)->get(route('admin.users.show', $this->victim))->assertNotFound();
        $this->actingAs($this->attacker)->get(route('admin.users.edit', $this->victim))->assertNotFound();

        // And the listing does not leak them either.
        $this->actingAs($this->attacker)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertDontSee('victim@example.test');
    }

    #[Test]
    public function the_same_operations_succeed_within_one_organisation(): void
    {
        // The guard must refuse the boundary, not the feature. Without this the
        // suite would pass just as well with every mutation broken.
        $ours = $this->accountIn(
            app(OrganisationContext::class)->require()->getKey(),
            'colleague@example.test',
            Role::Viewer,
        );

        $this->actingAs($this->attacker)
            ->post(route('admin.users.status', $ours), ['status' => LifecycleStatus::Disabled->value])
            ->assertRedirect(route('admin.users.show', $ours));

        $this->assertSame(LifecycleStatus::Disabled, $ours->refresh()->status);
    }
}
