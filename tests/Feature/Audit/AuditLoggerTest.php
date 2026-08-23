<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Enums\AuditOutcome;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Audit\Support\CorrelationId;
use App\Modules\Audit\Support\Redaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The audit trail: what it records, what it refuses to record, and what it
 * refuses to let anybody do to it afterwards.
 *
 * Release 1 gate 1 makes this infrastructure a prerequisite for every later
 * gate, so a regression here is not contained to one screen.
 */
class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    private function logger(): AuditLogger
    {
        return app(AuditLogger::class);
    }

    private function admin(): User
    {
        $user = User::query()->create(['name' => 'Ada Admin', 'email' => 'ada@example.test']);
        $user->forceFill(['role' => Role::SystemAdmin])->save();

        return $user->refresh();
    }

    #[Test]
    public function an_event_carries_everything_an_investigation_needs(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $event = $this->logger()->record(
            action: 'system.setting.updated',
            module: 'Platform',
            resourceType: 'system_setting',
            resourceId: 'app.display_name',
            before: ['value' => 'Old'],
            after: ['value' => 'New'],
            reason: 'Rebranding',
        );

        $this->assertNotNull($event);
        $this->assertSame('system.setting.updated', $event->action);
        $this->assertSame('Platform', $event->module);
        $this->assertSame(AuditOutcome::Succeeded, $event->outcome);
        $this->assertSame($admin->id, $event->actor_user_id);
        // The label outlives the account row, which is why it is stored as well
        // as the foreign key.
        $this->assertSame('ada@example.test', $event->actor_label);
        $this->assertSame(['value' => 'Old'], $event->before_summary);
        $this->assertSame(['value' => 'New'], $event->after_summary);
        $this->assertNotNull($event->correlation_id);
        $this->assertSame('testing', $event->environment);
    }

    #[Test]
    public function a_secret_handed_to_the_writer_never_reaches_the_table(): void
    {
        $this->actingAs($this->admin());

        // The writer is deliberately given something it should not store. There
        // is no parameter to skip redaction, and this proves it.
        $this->logger()->record(
            action: 'integration.updated',
            module: 'Integration',
            after: ['client_secret' => 'super-secret-value', 'base_url' => 'https://api.example.test'],
            reason: 'Rotated using token=abcdef1234567890',
        );

        $event = AuditEvent::query()->firstOrFail();

        $this->assertSame(Redaction::PLACEHOLDER, $event->after_summary['client_secret']);
        $this->assertSame('https://api.example.test', $event->after_summary['base_url']);
        $this->assertStringNotContainsString('abcdef1234567890', (string) $event->reason);

        // The whole row, not just the field we expected it in.
        $this->assertStringNotContainsString('super-secret-value', json_encode($event->toArray()) ?: '');
    }

    #[Test]
    public function an_existing_event_cannot_be_changed(): void
    {
        $this->actingAs($this->admin());
        $event = $this->logger()->record('user.disabled', 'Identity');

        $this->expectException(RuntimeException::class);

        // A row that can be updated is not evidence. It throws rather than
        // returning false, so the attempt is visible in the change that made it.
        $event->forceFill(['action' => 'user.enabled'])->save();
    }

    #[Test]
    public function an_existing_event_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $event = $this->logger()->record('user.disabled', 'Identity');

        $this->expectException(RuntimeException::class);

        $event->delete();
    }

    #[Test]
    public function a_refusal_is_recorded_as_well_as_a_success(): void
    {
        $this->actingAs($this->admin());

        $this->logger()->denied('privileged.action.denied', 'Platform', reason: 'Tier not held');

        $event = AuditEvent::query()->firstOrFail();

        // A trail containing only successes cannot show an attack that failed.
        $this->assertSame(AuditOutcome::Denied, $event->outcome);
        $this->assertSame('Tier not held', $event->reason);
    }

    #[Test]
    public function a_system_action_with_no_signed_in_person_is_still_recorded(): void
    {
        $event = $this->logger()->record('system.setting.updated', 'Platform');

        $this->assertNotNull($event);
        $this->assertNull($event->actor_user_id);
        $this->assertSame('system', $event->actor_type);
        // Never invented. A blank actor label is the honest answer.
        $this->assertNull($event->actor_label);
    }

    #[Test]
    public function every_event_in_one_request_shares_a_correlation_id(): void
    {
        $this->actingAs($this->admin());

        $first = $this->logger()->record('a.happened', 'Platform');
        $second = $this->logger()->record('b.happened', 'Platform');

        $this->assertSame($first?->correlation_id, $second?->correlation_id);
        $this->assertSame(CorrelationId::current(), $first?->correlation_id);
    }

    #[Test]
    public function the_response_carries_the_correlation_id_back_to_the_caller(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin');

        // ADM-024: an administrator seeing an error must have something precise
        // to quote. It is random and carries no information about them.
        $response->assertOk()->assertHeader(CorrelationId::HEADER);
    }

    #[Test]
    public function a_refused_privileged_route_leaves_a_trace(): void
    {
        $viewer = User::query()->create(['name' => 'Vic Viewer', 'email' => 'vic@example.test']);
        $viewer->forceFill(['role' => Role::Viewer])->save();

        $this->actingAs($viewer->refresh())->get('/admin')->assertForbidden();

        $event = AuditEvent::withoutOrganisationScope()
            ->where('action', 'privileged.action.denied')
            ->firstOrFail();

        // A 403 that leaves no trace is the most interesting event in the
        // application going unrecorded.
        $this->assertSame(AuditOutcome::Denied, $event->outcome);
        $this->assertSame('Security', $event->module);
        $this->assertSame('admin.overview', $event->resource_id);
        $this->assertSame($viewer->id, $event->actor_user_id);
        $this->assertStringContainsString('system-admin', (string) $event->reason);
    }

    #[Test]
    public function a_caller_supplied_correlation_id_is_only_accepted_when_it_is_a_uuid(): void
    {
        $response = $this->actingAs($this->admin())
            ->withHeader(CorrelationId::HEADER, "not-a-uuid\ninjected log line")
            ->get('/admin');

        // The id is echoed into logs and pages, so an unvalidated one would be a
        // log-injection foothold. Anything that is not a plain UUID is replaced.
        $returned = $response->headers->get(CorrelationId::HEADER);

        $this->assertNotSame("not-a-uuid\ninjected log line", $returned);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $returned);
    }
}
