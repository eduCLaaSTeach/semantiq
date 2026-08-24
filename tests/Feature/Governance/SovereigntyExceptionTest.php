<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Governance\Enums\ExceptionStatus;
use App\Modules\Governance\Models\SovereigntyException;
use App\Modules\Governance\Services\SovereigntyExceptions;
use App\Modules\Governance\Services\SovereigntyProfiles;
use App\Modules\Identity\Models\Organisation;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * ADM-016 Sovereignty Exceptions.
 *
 * The governance rules this feature exists to enforce, each tested because each
 * is a way an exception could quietly become a permanent weakening:
 *
 *   A requester never approves their own request.
 *   Approval sits at System Administrator.
 *   An end date is required and an expired exception stops applying by itself.
 *   A revoked exception stops applying immediately.
 *   No exception ever changes the underlying approved profile.
 *   Every decision leaves a trail with a stated reason.
 */
class SovereigntyExceptionTest extends TestCase
{
    use RefreshDatabase;

    private function personOn(Role $role): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);

        $user->forceFill([
            'role' => $role,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    /** An approved sovereignty profile to depart from. */
    private function approvedProfile(User $actor): void
    {
        $profiles = app(SovereigntyProfiles::class);
        $profiles->ensureDraft($actor);
        $profiles->approve($actor, 'Geographies confirmed with the hosting provider.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function aRequest(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Disaster recovery drill in Malaysia',
            'justification' => 'The provider cannot run the annual recovery drill inside Singapore this year.',
            'aspect' => 'backup',
            'requested_geography' => 'my',
            'ends_on' => Carbon::today()->addDays(30)->toDateString(),
        ], $overrides);
    }

    #[Test]
    public function a_request_permits_nothing_until_it_is_approved(): void
    {
        $requester = $this->personOn(Role::Admin);
        $exception = app(SovereigntyExceptions::class)->request($this->aRequest(), $requester);

        $this->assertSame(ExceptionStatus::Requested, $exception->status);
        $this->assertFalse($exception->isInForce());
        $this->assertSame(0, app(SovereigntyExceptions::class)->inForce()->count());
    }

    #[Test]
    public function a_requester_cannot_approve_their_own_request(): void
    {
        /*
         * THE CONTROL THE TIER SPLIT CANNOT EXPRESS. A System Administrator
         * holds both `.request` and `.approve`, so the permission model alone
         * permits this - and a person agreeing with themselves is not an
         * approval. SEC-DEC-067.
         */
        $person = $this->personOn(Role::SystemAdmin);
        $service = app(SovereigntyExceptions::class);

        $exception = $service->request($this->aRequest(), $person);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/you cannot decide it/');

        $service->approve($exception, $person, 'Approving my own request.');
    }

    #[Test]
    public function a_requester_cannot_reject_their_own_request_either(): void
    {
        /* Rejection is the same decision with the other outcome. Blocking only
         * approval would leave half the control. */
        $person = $this->personOn(Role::SystemAdmin);
        $service = app(SovereigntyExceptions::class);

        $exception = $service->request($this->aRequest(), $person);

        $this->expectException(RuntimeException::class);

        $service->reject($exception, $person, 'Rejecting my own request.');
    }

    #[Test]
    public function somebody_else_can_approve_it(): void
    {
        $requester = $this->personOn(Role::Admin);
        $approver = $this->personOn(Role::SystemAdmin);
        $service = app(SovereigntyExceptions::class);

        $exception = $service->request($this->aRequest(), $requester);
        $approved = $service->approve($exception, $approver, 'Reviewed and accepted for the drill window.');

        $this->assertSame(ExceptionStatus::Approved, $approved->status);
        $this->assertTrue($approved->isInForce());
    }

    #[Test]
    public function an_expired_exception_stops_applying_by_itself(): void
    {
        /*
         * No job runs. `isInForce()` combines the stored status with today's
         * date, so an exception lapses at midnight with nothing needing to have
         * executed - which is what keeps gate 4 free of a queue dependency.
         */
        $requester = $this->personOn(Role::Admin);
        $approver = $this->personOn(Role::SystemAdmin);
        $service = app(SovereigntyExceptions::class);

        $exception = $service->request($this->aRequest(['ends_on' => Carbon::today()->addDay()->toDateString()]), $requester);
        $service->approve($exception, $approver, 'Approved for a short window.');

        $this->assertTrue($exception->refresh()->isInForce());

        /* Two days later, with nothing having run. */
        Carbon::setTestNow(Carbon::now()->addDays(2));

        $this->assertFalse($exception->refresh()->isInForce());
        $this->assertTrue($exception->hasExpired());
        /* And its stored status still says approved, because nothing decided
         * otherwise - expiry is a fact about a date, not a decision. */
        $this->assertSame(ExceptionStatus::Approved, $exception->status);
        $this->assertSame(0, $service->inForce()->count());

        Carbon::setTestNow();
    }

    #[Test]
    public function an_exception_that_has_not_started_is_not_in_force(): void
    {
        $requester = $this->personOn(Role::Admin);
        $approver = $this->personOn(Role::SystemAdmin);
        $service = app(SovereigntyExceptions::class);

        $exception = $service->request($this->aRequest([
            'starts_on' => Carbon::today()->addDays(7)->toDateString(),
            'ends_on' => Carbon::today()->addDays(14)->toDateString(),
        ]), $requester);
        $service->approve($exception, $approver, 'Approved ahead of the window.');

        $this->assertFalse($exception->refresh()->isInForce());
        $this->assertTrue($exception->isPending());
    }

    #[Test]
    public function a_revoked_exception_stops_applying_immediately(): void
    {
        $requester = $this->personOn(Role::Admin);
        $approver = $this->personOn(Role::SystemAdmin);
        $service = app(SovereigntyExceptions::class);

        $exception = $service->request($this->aRequest(), $requester);
        $service->approve($exception, $approver, 'Approved for the drill window.');

        $this->assertTrue($exception->refresh()->isInForce());

        $service->revoke($exception, $approver, 'The drill was cancelled, so the exception is not needed.');

        $this->assertFalse($exception->refresh()->isInForce());
        $this->assertSame(ExceptionStatus::Revoked, $exception->status);
        $this->assertSame(0, $service->inForce()->count());
    }

    #[Test]
    public function only_an_approved_exception_can_be_revoked(): void
    {
        /* Revoking a request that was never approved would record that
         * something in force had been ended, when nothing ever was. */
        $requester = $this->personOn(Role::Admin);
        $approver = $this->personOn(Role::SystemAdmin);
        $service = app(SovereigntyExceptions::class);

        $exception = $service->request($this->aRequest(), $requester);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Only an approved exception/');

        $service->revoke($exception, $approver, 'Trying to revoke something never approved.');
    }

    #[Test]
    public function an_already_decided_exception_cannot_be_decided_again(): void
    {
        $requester = $this->personOn(Role::Admin);
        $approver = $this->personOn(Role::SystemAdmin);
        $service = app(SovereigntyExceptions::class);

        $exception = $service->request($this->aRequest(), $requester);
        $service->reject($exception, $approver, 'Not justified for this year.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already been decided/');

        $service->approve($exception->refresh(), $approver, 'Changing my mind after the fact.');
    }

    #[Test]
    public function every_decision_requires_a_stated_reason(): void
    {
        $requester = $this->personOn(Role::Admin);
        $approver = $this->personOn(Role::SystemAdmin);
        $service = app(SovereigntyExceptions::class);

        $exception = $service->request($this->aRequest(), $requester);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/requires a stated reason/');

        $service->approve($exception, $approver, '   ');
    }

    #[Test]
    public function no_exception_changes_the_approved_profile(): void
    {
        /*
         * The invariant the whole feature rests on. An exception that edited
         * the profile would make the approved position a lie, and a year later
         * would be indistinguishable from somebody approving a weaker position.
         */
        $requester = $this->personOn(Role::Admin);
        $approver = $this->personOn(Role::SystemAdmin);

        $this->approvedProfile($approver);

        $profiles = app(SovereigntyProfiles::class);
        $before = $profiles->inForce()->only([
            'version', 'storage_geography', 'processing_geography',
            'backup_geography', 'external_replication',
            'cross_geo_storage', 'cross_geo_processing',
        ]);

        $service = app(SovereigntyExceptions::class);
        $exception = $service->request($this->aRequest(), $requester);
        $service->approve($exception, $approver, 'Approved for the drill window.');

        $after = $profiles->inForce()->only(array_keys($before));

        $this->assertSame($before, $after, 'Approving an exception altered the sovereignty profile.');
    }

    #[Test]
    public function the_exception_records_which_profile_version_it_departs_from(): void
    {
        $requester = $this->personOn(Role::Admin);
        $approver = $this->personOn(Role::SystemAdmin);

        $this->approvedProfile($approver);

        $exception = app(SovereigntyExceptions::class)->request($this->aRequest(), $requester);

        $this->assertSame(1, $exception->profile?->version);
    }

    #[Test]
    public function every_decision_leaves_a_trail_with_its_reason(): void
    {
        $requester = $this->personOn(Role::Admin);
        $approver = $this->personOn(Role::SystemAdmin);
        $service = app(SovereigntyExceptions::class);

        $exception = $service->request($this->aRequest(), $requester);
        $service->approve($exception, $approver, 'Reviewed and accepted for the drill window.');
        $service->revoke($exception->refresh(), $approver, 'The drill was cancelled.');

        foreach (['requested', 'approved', 'revoked'] as $step) {
            $event = AuditEvent::query()
                ->where('action', 'governance.sovereignty_exception.'.$step)
                ->first();

            $this->assertNotNull($event, "No trail for `{$step}`.");
            $this->assertNotEmpty($event->reason, "The `{$step}` event has no reason.");
        }

        /* And the summary holds real values rather than "[redacted]".
         * `requested_geography` is named that way precisely so it survives the
         * audit redactor. SEC-DEC-044. */
        $approved = AuditEvent::query()
            ->where('action', 'governance.sovereignty_exception.approved')->first();

        $this->assertSame('my', ((array) $approved->after_summary)['requested_geography'] ?? null);
    }

    #[Test]
    public function one_organisation_cannot_see_another_organisations_exceptions(): void
    {
        /*
         * The tenancy boundary, exercised the way `CrossOrganisationTest` does:
         * write under one organisation, bind to a second, and assert nothing
         * crosses. A sovereignty exception names where another customer's data
         * is allowed to go, which makes leaking one across the boundary about
         * as bad as this schema gets.
         */
        $requester = $this->personOn(Role::Admin);
        app(SovereigntyExceptions::class)->request($this->aRequest(), $requester);

        $this->assertSame(1, SovereigntyException::query()->count());
        $this->assertSame(1, app(SovereigntyExceptions::class)->all()->count());

        $second = Organisation::query()->forceCreate([
            'code' => 'SECOND', 'name' => 'Second Customer', 'status' => 'active', 'version' => 1,
        ]);

        app(OrganisationContext::class)->forget();
        app(OrganisationContext::class)->bind($second);

        $this->assertSame(0, SovereigntyException::query()->count());
        $this->assertSame(0, app(SovereigntyExceptions::class)->all()->count());
        $this->assertSame(0, app(SovereigntyExceptions::class)->inForce()->count());
    }
}
