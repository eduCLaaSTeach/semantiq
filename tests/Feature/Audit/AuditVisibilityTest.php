<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Projection\AuditProjection;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\EmitsEvidence;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * A6, A10, A14, A18. WHO SEES WHICH ROWS, AND WHICH FIELDS OF THEM.
 *
 * TWO SEPARATE QUESTIONS. P1-06 conflated them once and showed an Auditor -
 * whose whole role is reading evidence - an empty screen.
 */
final class AuditVisibilityTest extends TestCase
{
    use EmitsEvidence;
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    /**
     * A14. PLATFORM ROWS ARE SYSTEM ADMINISTRATOR ONLY, and a row belonging to
     * another organisation is nobody else's.
     *
     * Mutation: drop the `orWhereNull` branch - the System Administrator loses
     * platform evidence. Or omit the WHERE for them - every organisation leaks.
     */
    public function test_platform_evidence_is_system_administrator_only(): void
    {
        [$organisation, $admin, $orgAdmin, $auditor] = $this->threeViewers();
        $other = $this->make->organisation('Other');

        AuditEvent::query()->delete();
        $this->platformEvent();
        $this->organisationEvent($organisation->id);
        $this->organisationEvent($other->id);

        $this->assertSame(2, $this->visibleTo($admin, $organisation->id));
        $this->assertSame(1, $this->visibleTo($orgAdmin, $organisation->id));
        $this->assertSame(1, $this->visibleTo($auditor, $organisation->id));
    }

    /** A deactivated viewer sees nothing, whatever they once held. */
    public function test_a_deactivated_viewer_sees_nothing(): void
    {
        [$organisation, , $orgAdmin] = $this->threeViewers();

        AuditEvent::query()->delete();
        $this->organisationEvent($organisation->id);

        $orgAdmin->forceFill(['status' => 'inactive'])->save();

        $this->assertSame(0, $this->visibleTo($orgAdmin->fresh(), $organisation->id));
    }

    /**
     * A6. RESTRICTED FIELDS ARE ABSENT FROM THE RENDERED PAYLOAD, not null.
     *
     * ASSERTED ON WHAT THE SCREEN RECEIVES. "It is not rendered" and "it is not
     * present" look identical from the session, and only the second is a
     * control - a null is a value somebody later sorts by, colours or prints.
     *
     * Mutation: return the real value with a `null` for unauthorised viewers.
     */
    public function test_platform_sensitive_identifiers_are_withheld(): void
    {
        [$organisation, $admin, $orgAdmin] = $this->threeViewers();

        AuditEvent::query()->delete();

        $this->emit(SecurityEventLogger::LOGIN_REFUSED_UNKNOWN, [
            'provider' => 'microsoft',
            'subject' => 'outsider-directory-guid',
            'tenant' => 'a-tenant-guid',
            'result' => 'refused',
            'reason' => 'unknown_identity',
        ]);

        $row = AuditEvent::query()->sole();
        $projection = app(AuditProjection::class);
        $name = static fn (int $id): string => 'Somebody';

        $privileged = $projection->row($row, $admin, $name);
        $ordinary = $projection->row($row, $orgAdmin, $name);

        $this->assertSame('outsider-directory-guid', $privileged['directorySubject']);
        $this->assertSame('a-tenant-guid', $privileged['directoryTenant']);

        $this->assertSame(AuditProjection::WITHHELD, $ordinary['directorySubject']);
        $this->assertSame(AuditProjection::WITHHELD, $ordinary['directoryTenant']);

        // THE KEY SET IS INVARIANT. A payload whose SHAPE changed with the
        // viewer's authority would itself be the disclosure.
        $this->assertSame(array_keys($privileged), array_keys($ordinary));
    }

    /** A refused sign-in is never rendered as a SemantIQ person. */
    public function test_an_external_actor_is_never_given_a_name(): void
    {
        [, $admin] = $this->threeViewers();

        AuditEvent::query()->delete();

        $this->emit(SecurityEventLogger::LOGIN_REFUSED_TENANT, [
            'provider' => 'microsoft', 'subject' => 'guid', 'tenant' => 'other',
            'result' => 'refused', 'reason' => 'tenant',
        ]);

        $row = app(AuditProjection::class)->row(
            AuditEvent::query()->sole(),
            $admin,
            static fn (int $id): string => 'A Real Person',
        );

        $this->assertSame('An account outside SemantIQ', $row['actor']);
        $this->assertNotSame('A Real Person', $row['actor']);
    }

    /**
     * A10 / D-108. Somebody without evidence authority is refused IDENTICALLY
     * whether or not there is anything to see, so the refusal is not an oracle
     * for whether evidence exists.
     *
     * Mutation: return a different status when the log is empty.
     */
    public function test_an_unauthorised_viewer_learns_nothing_from_the_refusal(): void
    {
        $organisation = $this->make->organisation();
        $outsider = $this->make->user($organisation);
        $this->access->assignment($outsider, RoleCode::BusinessUser, $organisation);

        AuditEvent::query()->delete();
        $empty = $this->signedInAs($outsider)->get('/console/audit');

        $this->organisationEvent($organisation->id);
        $populated = $this->signedInAs($outsider)->get('/console/audit');

        $this->assertSame($empty->status(), $populated->status());
        $this->assertSame(
            $empty->headers->get('Location'),
            $populated->headers->get('Location'),
            'The refusal told an outsider whether there was anything to see.'
        );
    }

    /**
     * A18. The evidence start date is on the screen. D-109.
     *
     * Mutation: remove it from the payload. Somebody then reads an empty first
     * month as "nothing happened".
     */
    public function test_the_evidence_start_date_is_on_every_tab(): void
    {
        [, $admin] = $this->threeViewers();

        foreach ([
            '/console/audit',
            '/console/audit/admin-changes',
            '/console/audit/security-events',
            '/console/audit/configuration',
        ] as $path) {
            $props = $this->signedInAs($admin)->get($path)->viewData('page')['props'];

            $this->assertNotNull($props['evidenceStart'], "{$path} does not say when the evidence begins.");
            $this->assertTrue($props['chain']['intact']);
        }
    }

    /** @return array{0: object, 1: User, 2: User, 3: User} */
    private function threeViewers(): array
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $orgAdmin = $this->make->user($organisation);
        $this->access->assignment($orgAdmin, RoleCode::OrganisationAdministrator, $organisation);

        $auditor = $this->make->user($organisation);
        $this->access->assignment($auditor, RoleCode::Auditor, $organisation);

        return [$organisation, $admin->fresh(), $orgAdmin->fresh(), $auditor->fresh()];
    }

    private function visibleTo(User $viewer, ?int $organisationId): int
    {
        $query = AuditEvent::query();
        app(AuditProjection::class)->scopeVisible($query, $viewer, $organisationId);

        return $query->count();
    }

    private function platformEvent(): void
    {
        $this->emit(SecurityEventLogger::BOOTSTRAP_REFUSED, [
            'result' => 'refused', 'reason' => 'bootstrap_closed',
        ]);
    }

    private function organisationEvent(int $organisationId): void
    {
        $this->emit(SecurityEventLogger::ORGANISATION_UPDATED, [
            'user_id' => 1, 'organisation_id' => $organisationId, 'result' => 'updated',
        ]);
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
