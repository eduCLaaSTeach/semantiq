<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Support\Redaction;
use App\Modules\Identity\Models\Organisation;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Security\Models\SecurityPolicy;
use App\Modules\Security\Support\SecurityPolicies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ControlsSecurityPolicy;
use Tests\TestCase;

/**
 * ADM-009 to ADM-011, the policy store itself.
 *
 * Six guards are being proved here, and they are the reason `security_policies`
 * is written through a service rather than a model: the catalogue is the only
 * source of truth about what a policy is, no credential can be stored through
 * this path, a value is validated wherever it comes from, the editing tier is
 * checked for callers that never pass a route, a high-risk change needs a
 * reason, and every change lands in the audit trail.
 */
class SecurityPolicyServiceTest extends TestCase
{
    use ControlsSecurityPolicy, RefreshDatabase;

    private function policies(): SecurityPolicies
    {
        return app(SecurityPolicies::class);
    }

    private function admin(string $email = 'ada@example.test'): User
    {
        $user = User::query()->create(['name' => 'Ada Admin', 'email' => $email]);
        $user->forceFill([
            'role' => Role::SystemAdmin,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    private function personOn(Role $role): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);
        $user->forceFill([
            'role' => $role,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    #[Test]
    public function an_unset_policy_reads_as_its_catalogue_default(): void
    {
        // No seeder runs in production, so the default has to come from the
        // catalogue rather than from a row somebody remembered to insert.
        $this->assertSame(0, SecurityPolicy::query()->count());
        $this->assertSame(120, $this->policies()->get('activity.idle_minutes'));
        $this->assertFalse($this->policies()->get('api.hsts_enabled'));
    }

    #[Test]
    public function hsts_is_off_by_default_and_short_lived_when_switched_on(): void
    {
        // Gate 3 rule 8. HSTS is the one header here that cannot be withdrawn
        // from a browser that has already seen it, so it ships off and its
        // duration ships at one day rather than the year the specification
        // suggests: a short max-age is the mistake you can recover from.
        $this->assertFalse($this->policies()->enabled('api.hsts_enabled'));
        $this->assertSame(1, $this->policies()->number('api.hsts_max_age_days'));
    }

    #[Test]
    public function an_unknown_key_throws_rather_than_reading_as_null(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // A typo that read as null would be a security control silently taking
        // its fallback path.
        $this->policies()->get('sign_in.does_not_exist');
    }

    #[Test]
    public function a_high_risk_change_is_refused_without_a_reason(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        try {
            $this->policies()->set('activity.idle_minutes', 30, $admin);
            $this->fail('A high-risk change was accepted with no reason given.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('needs a reason', $exception->getMessage());
        }

        // Nothing was written, and the value did not move.
        $this->assertSame(0, SecurityPolicy::query()->count());
        $this->assertSame(120, $this->policies()->get('activity.idle_minutes'));

        $this->assertTrue($this->policies()->set('activity.idle_minutes', 30, $admin, 'Tightened after review.'));
        $this->assertSame(30, $this->policies()->get('activity.idle_minutes'));
    }

    #[Test]
    public function a_low_risk_change_does_not_need_one(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // The reason requirement has to be selective or it becomes a box people
        // type "update" into, which is worse than not asking.
        $this->assertTrue($this->policies()->set('sign_in.lock_minutes', 15, $admin));
        $this->assertSame(15, $this->policies()->get('sign_in.lock_minutes'));
    }

    #[Test]
    public function a_value_outside_the_catalogue_rules_is_refused(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // Validated in the SERVICE, not only in the form request: a console
        // command and a queued job reach this class without passing a request,
        // and an idle timeout of minus one is a session that never expires.
        $this->expectException(InvalidArgumentException::class);

        $this->policies()->set('activity.idle_minutes', -1, $admin, 'Because I can.');
    }

    #[Test]
    public function a_choice_outside_its_declared_options_is_refused(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // The catalogue's rules say "string", so without the extra check a
        // crafted post could set an authentication mode this application does
        // not implement - which would resolve to no branch at all.
        $this->expectException(InvalidArgumentException::class);

        $this->policies()->set('sign_in.mode', 'no_authentication_at_all', $admin, 'Trying it on.');
    }

    #[Test]
    public function a_credential_shaped_value_is_refused_and_the_refusal_is_audited(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        try {
            $this->policies()->set(
                'sign_in.allowed_email_domains',
                'Authorization: Bearer abcdefghijklmnopqrstuvwxyz012345',
                $admin,
                'Pasting the wrong thing.',
            );
            $this->fail('A credential-shaped value was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('looks like a credential', $exception->getMessage());
        }

        $this->assertSame(0, SecurityPolicy::query()->count());

        $denial = AuditEvent::query()->where('action', 'security.policy.updated')->firstOrFail();
        $this->assertSame('denied', $denial->outcome->value);

        // And the trail does not contain the thing it refused.
        $this->assertStringNotContainsString('abcdefghijklmnopqrstuvwxyz', (string) $denial->reason);
    }

    #[Test]
    public function an_actor_below_the_editing_tier_is_refused_at_the_service(): void
    {
        $admin = $this->personOn(Role::Admin);
        $this->actingAs($admin);

        try {
            $this->policies()->set('activity.idle_minutes', 30, $admin, 'Trying.');
            $this->fail('An Administrator changed a System Administrator policy.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('do not have authority', $exception->getMessage());
        }

        $denial = AuditEvent::query()->where('action', 'security.policy.updated')->firstOrFail();
        $this->assertSame('denied', $denial->outcome->value);
        $this->assertSame(120, $this->policies()->get('activity.idle_minutes'));
    }

    #[Test]
    public function every_change_is_audited_with_the_old_and_new_value_and_the_reason(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->policies()->set('activity.idle_minutes', 45, $admin, 'Tightened after the access review.');

        $event = AuditEvent::query()->where('action', 'security.policy.updated')->firstOrFail();

        $this->assertSame('Security', $event->module);
        $this->assertSame('activity.idle_minutes', $event->resource_id);
        $this->assertSame(120, $event->before_summary['value']);
        $this->assertSame(45, $event->after_summary['value']);
        $this->assertSame('Tightened after the access review.', $event->reason);
    }

    #[Test]
    public function the_audit_summary_of_a_policy_change_is_readable_rather_than_redacted(): void
    {
        // The reason the key prefixes are `sign_in.`, `activity.` and `api.`
        // rather than `auth.` and `session.`. Redaction::summarise() replaces
        // the value of any key containing "auth" or "session", so a policy
        // stored under those names would record "[redacted] -> [redacted]" and
        // the trail would be useless for exactly the settings an auditor comes
        // looking for. SEC-DEC-044.
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->policies()->set('activity.maximum_minutes', 240, $admin, 'Shorter working day.');

        $event = AuditEvent::query()->where('action', 'security.policy.updated')->firstOrFail();

        $this->assertNotSame('[redacted]', $event->before_summary['value']);
        $this->assertNotSame('[redacted]', $event->after_summary['value']);
        $this->assertSame(240, $event->after_summary['value']);
    }

    #[Test]
    public function writing_the_same_value_changes_nothing_and_is_not_audited(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // A trail full of non-changes is a trail nobody reads, and on a
        // security screen that matters more rather than less.
        $this->assertFalse($this->policies()->set('activity.idle_minutes', 120, $admin, 'No change.'));
        $this->assertSame(0, AuditEvent::query()->where('action', 'security.policy.updated')->count());
    }

    #[Test]
    public function a_policy_needing_a_capability_the_environment_lacks_is_reported_as_not_in_force(): void
    {
        // Decision D3. The value is stored and honest; the claim that it is
        // protecting something would not be. `get()` and `inForce()` answer
        // two different questions on purpose.
        config(['session.driver' => 'file']);

        $this->assertFalse($this->policies()->inForce('activity.revocation_enabled'));
        $this->assertStringContainsString('cannot list', (string) $this->policies()->blocker('activity.revocation_enabled'));

        config(['session.driver' => 'database']);
        app()->forgetInstance(SecurityPolicies::class);

        $this->assertTrue(app(SecurityPolicies::class)->inForce('activity.revocation_enabled'));
        $this->assertNull(app(SecurityPolicies::class)->blocker('activity.revocation_enabled'));
    }

    #[Test]
    public function a_policy_with_no_capability_requirement_is_always_in_force(): void
    {
        $this->assertTrue($this->policies()->inForce('activity.idle_minutes'));
        $this->assertNull($this->policies()->blocker('activity.idle_minutes'));
    }

    #[Test]
    public function one_organisation_cannot_read_or_write_anothers_policy(): void
    {
        $ourOrganisation = app(OrganisationContext::class)->require();
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->policies()->set('activity.idle_minutes', 45, $admin, 'Ours.');

        $other = Organisation::query()->forceCreate([
            'code' => 'OTHER', 'name' => 'Other Customer', 'status' => 'active', 'version' => 1,
        ]);

        // Written directly, because the service would refuse to write outside
        // the organisation in force - which is the point.
        SecurityPolicy::query()->forceCreate([
            'organisation_id' => $other->getKey(),
            'key' => 'activity.idle_minutes',
            'value' => '999',
        ]);

        // A second organisation now exists, so automatic resolution
        // deliberately stops. The context is bound explicitly to ours.
        app(OrganisationContext::class)->forget();
        app(OrganisationContext::class)->bind($ourOrganisation);
        app()->forgetInstance(SecurityPolicies::class);

        // The global scope keeps the other customer's row out of the answer.
        $this->assertSame(45, app(SecurityPolicies::class)->get('activity.idle_minutes'));

        $this->assertSame(
            $ourOrganisation->getKey(),
            SecurityPolicy::query()->where('key', 'activity.idle_minutes')->firstOrFail()->organisation_id,
        );
    }

    #[Test]
    public function every_catalogue_key_survives_the_audit_redactor(): void
    {
        // A structural guard rather than a behaviour one. Adding a key called
        // `session.timeout` or `auth.mode` would compile, work, and quietly
        // record every change to it as "[redacted]". This fails the moment
        // somebody does.
        $offenders = [];

        foreach (array_keys((array) config('security.policies', [])) as $key) {
            if (Redaction::isSensitiveKey($key)) {
                $offenders[] = $key;
            }
        }

        $this->assertSame([], $offenders, 'These policy keys would be redacted out of their own audit trail: '
            .implode(', ', $offenders));
    }
}
