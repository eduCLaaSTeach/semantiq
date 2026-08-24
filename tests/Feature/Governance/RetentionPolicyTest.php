<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Governance\Enums\RetentionStatus;
use App\Modules\Governance\Models\RetentionPolicy;
use App\Modules\Governance\Services\PersonalDataCatalogue;
use App\Modules\Governance\Services\RetentionPolicies;
use App\Modules\Identity\Models\Organisation;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * PDPA-03 Per-category Retention.
 *
 * THE MOST IMPORTANT TEST IN THIS FILE IS `no_deletion_path_exists`. Everything
 * else describes a policy store; that one asserts the policy store cannot
 * become an execution engine by accident. SEC-DEC-038.
 *
 * The rest hold the honesty of the screen in place: an unset period reads Not
 * Configured rather than as a default, a period cannot be approved without
 * being set, and editing an approved policy returns it to draft because a
 * period that changed after approval is not the period anybody approved.
 */
class RetentionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function personOn(Role $role = Role::Admin): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);

        $user->forceFill([
            'role' => $role,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    private function aCategory(User $actor)
    {
        return app(PersonalDataCatalogue::class)->all($actor)->firstWhere('code', 'account_identity');
    }

    #[Test]
    public function every_active_category_appears_even_with_no_policy(): void
    {
        /*
         * A category with no policy is the state that matters most - personal
         * data nobody has decided a period for - so the list walks CATEGORIES
         * and pairs each with its policy or with nothing. Listing only the
         * policies that exist would hide exactly that.
         */
        $actor = $this->personOn();
        app(PersonalDataCatalogue::class)->all($actor);

        $rows = app(RetentionPolicies::class)->forEveryCategory();

        $this->assertSame(7, $rows->count());
        $this->assertTrue($rows->every(static fn (array $r): bool => $r['policy'] === null));
        $this->assertSame(7, app(RetentionPolicies::class)->categoriesWithoutAPeriod());
    }

    #[Test]
    public function an_unset_period_reads_as_not_configured(): void
    {
        /* Never "forever", never a default, never the seven years this
         * repository once applied to everything. */
        $actor = $this->personOn();
        $category = $this->aCategory($actor);

        $policy = app(RetentionPolicies::class)->save($category, ['owner' => 'Priya Nair'], $actor);

        $this->assertFalse($policy->hasPeriod());
        $this->assertSame('Not Configured', $policy->periodLabel());
    }

    #[Test]
    public function a_policy_with_no_period_cannot_be_approved(): void
    {
        /*
         * Refused rather than allowed with a warning. An approved row that says
         * nothing is worse than a draft that plainly does not, because the
         * approved badge is what a reader trusts.
         */
        $actor = $this->personOn(Role::SystemAdmin);
        $category = $this->aCategory($actor);
        $service = app(RetentionPolicies::class);

        $policy = $service->save($category, ['owner' => 'Priya Nair'], $actor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no retention period/');

        $service->approve($policy, $actor, 'Approving an empty policy.');
    }

    #[Test]
    public function a_complete_policy_can_be_approved(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $category = $this->aCategory($actor);
        $service = app(RetentionPolicies::class);

        $policy = $service->save($category, [
            'retention_months' => 84,
            'basis' => 'Retained for seven years under the customer contract.',
            'lawful_basis' => 'Contract',
            'start_event' => 'account_closed',
            'disposal_action' => 'anonymise',
            'owner' => 'Priya Nair',
            'next_review_on' => Carbon::today()->addYear()->toDateString(),
        ], $actor);

        $approved = $service->approve($policy, $actor, 'Confirmed with the compliance owner.');

        $this->assertSame(RetentionStatus::Approved, $approved->status);
        $this->assertSame('7 years', $approved->periodLabel());
        $this->assertSame([], $approved->gaps());
    }

    #[Test]
    public function editing_an_approved_policy_returns_it_to_draft(): void
    {
        /*
         * A period that changed after somebody approved it is not the period
         * they approved. Leaving the approved badge in place would attribute a
         * decision to a person who did not make it.
         */
        $actor = $this->personOn(Role::SystemAdmin);
        $category = $this->aCategory($actor);
        $service = app(RetentionPolicies::class);

        $policy = $service->save($category, [
            'retention_months' => 84,
            'basis' => 'Seven years under the customer contract.',
            'start_event' => 'account_closed',
        ], $actor);
        $service->approve($policy, $actor, 'Confirmed with the compliance owner.');

        $revised = $service->save($category, ['retention_months' => 36], $actor);

        $this->assertSame(RetentionStatus::Draft, $revised->status);
        $this->assertNull($revised->approved_at);
        $this->assertNull($revised->approved_by_user_id);
    }

    #[Test]
    public function approving_requires_a_stated_reason(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $category = $this->aCategory($actor);
        $service = app(RetentionPolicies::class);

        $policy = $service->save($category, ['retention_months' => 12], $actor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/requires a stated reason/');

        $service->approve($policy, $actor, '  ');
    }

    #[Test]
    public function the_gaps_name_the_compliance_owned_fields(): void
    {
        $actor = $this->personOn();
        $category = $this->aCategory($actor);

        $policy = app(RetentionPolicies::class)->save($category, ['retention_months' => 12], $actor);

        $joined = implode(' ', $policy->gaps());

        $this->assertStringContainsString('basis', $joined);
        $this->assertStringContainsString('compliance-owned', $joined);
        $this->assertStringContainsString('start event', $joined);
    }

    #[Test]
    public function an_overdue_review_is_derived_from_the_date(): void
    {
        /* No job marks a review overdue. It is a question about today. */
        $actor = $this->personOn();
        $category = $this->aCategory($actor);
        $service = app(RetentionPolicies::class);

        $policy = $service->save($category, [
            'retention_months' => 12,
            'next_review_on' => Carbon::today()->addDay()->toDateString(),
        ], $actor);

        $this->assertFalse($policy->reviewIsOverdue());
        $this->assertSame(0, $service->overdueReviews()->count());

        Carbon::setTestNow(Carbon::now()->addDays(3));

        $this->assertTrue($policy->refresh()->reviewIsOverdue());
        $this->assertSame(1, $service->overdueReviews()->count());

        Carbon::setTestNow();
    }

    #[Test]
    public function one_policy_per_category(): void
    {
        /* Two would be two answers to one question. */
        $actor = $this->personOn();
        $category = $this->aCategory($actor);
        $service = app(RetentionPolicies::class);

        $service->save($category, ['retention_months' => 12], $actor);
        $service->save($category, ['retention_months' => 24], $actor);

        $this->assertSame(1, RetentionPolicy::query()->where('personal_data_category_id', $category->getKey())->count());
        $this->assertSame(24, $service->findForCategory((int) $category->getKey())?->retention_months);
    }

    #[Test]
    public function no_deletion_path_exists(): void
    {
        /*
         * THE MOST IMPORTANT ASSERTION IN THIS FILE. SEC-DEC-038.
         *
         * Gate 4 records retention policy and executes none of it. A retention
         * sweep is the single most destructive feature this application could
         * have, and it is not being built by the batch that first writes the
         * periods down. This asserts the service exposes no way to.
         */
        $methods = get_class_methods(RetentionPolicies::class);

        foreach ($methods as $method) {
            $this->assertDoesNotMatchRegularExpression(
                '/delete|destroy|purge|sweep|dispose|erase|expire/i',
                $method,
                "RetentionPolicies::{$method}() looks like it executes retention. Gate 4 stores policy only."
            );
        }

        /* And no governance route offers one either. */
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'admin.governance.retention')) {
                continue;
            }

            $this->assertNotContains('DELETE', $route->methods(), "The route `{$name}` accepts DELETE.");
        }
    }

    #[Test]
    public function one_organisation_cannot_see_another_organisations_retention(): void
    {
        $actor = $this->personOn();
        $category = $this->aCategory($actor);
        app(RetentionPolicies::class)->save($category, ['retention_months' => 12], $actor);

        $this->assertSame(1, RetentionPolicy::query()->count());

        $second = Organisation::query()->forceCreate([
            'code' => 'SECOND', 'name' => 'Second Customer', 'status' => 'active', 'version' => 1,
        ]);

        app(OrganisationContext::class)->forget();
        app(OrganisationContext::class)->bind($second);

        $this->assertSame(0, RetentionPolicy::query()->count());
        $this->assertSame(0, app(RetentionPolicies::class)->forEveryCategory()->count());
    }

    #[Test]
    public function a_retention_change_is_audited_with_real_values(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $category = $this->aCategory($actor);
        $service = app(RetentionPolicies::class);

        $policy = $service->save($category, [
            'retention_months' => 84,
            'lawful_basis' => 'Contract',
            'start_event' => 'account_closed',
            'disposal_action' => 'anonymise',
        ], $actor);
        $service->approve($policy, $actor, 'Confirmed with the compliance owner.');

        $event = AuditEvent::query()->where('action', 'governance.retention_policy.approved')->first();

        $this->assertNotNull($event);

        $summary = (array) $event->after_summary;

        /* Real values, not "[redacted]". `lawful_basis`, `start_event` and
         * `disposal_action` were all checked against the redactor before they
         * were named. SEC-DEC-044. */
        $this->assertSame(84, $summary['retention_months'] ?? null);
        $this->assertSame('Contract', $summary['lawful_basis'] ?? null);
        $this->assertSame('account_closed', $summary['start_event'] ?? null);
        $this->assertSame('anonymise', $summary['disposal_action'] ?? null);
    }
}
