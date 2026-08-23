<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The promotion command.
 *
 * It grants administrator authority against a production database, so the
 * behaviour worth pinning is what it REFUSES: an unknown account, an invalid
 * tier, an invalid domain, and a run with nothing to do. Each of those failing
 * loudly is what makes it safer than the tinker session it replaces.
 */
class PromoteUserTest extends TestCase
{
    use RefreshDatabase;

    private function person(): User
    {
        return User::query()->create(['name' => 'Test Person', 'email' => 'person@example.test']);
    }

    #[Test]
    public function it_grants_a_tier_the_auditor_flag_and_domains(): void
    {
        $user = $this->person();

        $this->artisan('semantiq:promote', [
            'email' => 'person@example.test',
            '--role' => 'system_admin',
            '--auditor' => true,
            '--domain' => ['sales', 'finance'],
            '--force' => true,
        ])->assertExitCode(0);

        $user->refresh();

        $this->assertSame(Role::SystemAdmin, $user->role);
        $this->assertTrue($user->is_auditor);
        $this->assertSame([BusinessDomain::Sales, BusinessDomain::Finance], $user->entitledDomains());
    }

    #[Test]
    public function all_grants_every_domain(): void
    {
        $this->person();

        $this->artisan('semantiq:promote', [
            'email' => 'person@example.test',
            '--domain' => ['all'],
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertCount(count(BusinessDomain::cases()), User::first()->entitledDomains());
    }

    #[Test]
    public function granting_a_domain_twice_is_not_two_grants(): void
    {
        $this->person();

        foreach ([1, 2] as $ignored) {
            $this->artisan('semantiq:promote', [
                'email' => 'person@example.test', '--domain' => ['sales'], '--force' => true,
            ])->assertExitCode(0);
        }

        $this->assertSame(1, User::first()->domainEntitlements()->count());
    }

    #[Test]
    public function the_auditor_capability_can_be_removed(): void
    {
        $user = $this->person();
        $user->forceFill(['is_auditor' => true])->save();

        $this->artisan('semantiq:promote', [
            'email' => 'person@example.test', '--no-auditor' => true, '--force' => true,
        ])->assertExitCode(0);

        $this->assertFalse($user->refresh()->is_auditor);
    }

    #[Test]
    public function an_unknown_account_is_refused_with_an_explanation(): void
    {
        // The likely reason on a fresh deployment is that nobody has signed in
        // yet, so the message says so rather than just "not found".
        $this->artisan('semantiq:promote', ['email' => 'nobody@example.test', '--role' => 'admin', '--force' => true])
            ->expectsOutputToContain('No account found')
            ->assertExitCode(1);
    }

    #[Test]
    public function an_invalid_tier_is_refused_and_changes_nothing(): void
    {
        $user = $this->person();

        $this->artisan('semantiq:promote', [
            'email' => 'person@example.test', '--role' => 'superuser', '--force' => true,
        ])->assertExitCode(1);

        $this->assertSame(Role::Viewer, $user->refresh()->role);
    }

    #[Test]
    public function an_invalid_domain_is_refused_and_changes_nothing(): void
    {
        $user = $this->person();

        $this->artisan('semantiq:promote', [
            'email' => 'person@example.test',
            '--role' => 'admin',
            '--domain' => ['marketing'],
            '--force' => true,
        ])->assertExitCode(1);

        // The role must not be applied either: a run that fails validation
        // makes no change at all, rather than half of one.
        $this->assertSame(Role::Viewer, $user->refresh()->role);
    }

    #[Test]
    public function a_run_with_nothing_to_change_is_refused(): void
    {
        $this->person();

        $this->artisan('semantiq:promote', ['email' => 'person@example.test', '--force' => true])
            ->expectsOutputToContain('Nothing to change')
            ->assertExitCode(1);
    }

    #[Test]
    public function declining_the_confirmation_changes_nothing(): void
    {
        $user = $this->person();

        // Granting an administrator role is a high-impact action under
        // ROLE_MODEL.md section 6, so it asks before writing.
        $this->artisan('semantiq:promote', ['email' => 'person@example.test', '--role' => 'system_admin'])
            ->expectsConfirmation('Apply this change?', 'no')
            ->assertExitCode(0);

        $this->assertSame(Role::Viewer, $user->refresh()->role);
    }
}
