<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Enums\AuditOutcome;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Audit\Support\AuditLogQuery;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADM-013 Audit Log, and DEC-004's four functional views.
 *
 * THE TWO SECURITY-CRITICAL PROPERTIES, and both are about what the screen must
 * NOT show:
 *
 *   The network identifier is not merely hidden for a reader without
 *   `admin.audit.view_network` - it is NOT SELECTED. A masked value has already
 *   been read out of the database and is one careless dump away from being
 *   visible; a column that was never fetched cannot leak.
 *
 *   Redaction still holds. `before_summary`, `after_summary` and `reason` were
 *   redacted at WRITE time, and this screen must not somehow reconstitute what
 *   the redactor removed.
 *
 * AND THE PROPERTY THAT MAKES THE PRESETS SAFE: they overlap rather than
 * partitioning the table. A reader who picks the wrong view must still find the
 * event by widening to All Events, so no preset can hide something for good.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function personOn(Role $role, bool $auditor = false): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);

        $user->forceFill([
            'role' => $role,
            'is_auditor' => $auditor,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    /** One event per module, so every preset has something to find. */
    private function anEventInEveryModule(User $actor): void
    {
        Auth::login($actor);

        $audit = app(AuditLogger::class);

        $audit->record(action: 'auth.sign_in.succeeded', module: 'Security', resourceType: 'user', resourceId: 1);
        $audit->record(action: 'user.disabled', module: 'Identity', resourceType: 'user', resourceId: 2);
        $audit->record(action: 'setting.updated', module: 'Platform', resourceType: 'system_setting', resourceId: 3);
        $audit->record(action: 'governance.retention_policy.updated', module: 'Governance', resourceType: 'retention_policy', resourceId: 4);
        $audit->record(action: 'security.policy.updated', module: 'Security', resourceType: 'security_policy', resourceId: 5);
        $audit->record(action: 'user.role.granted', module: 'Identity', outcome: AuditOutcome::Denied, resourceType: 'user', resourceId: 6);
    }

    #[Test]
    public function all_events_shows_everything(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $this->anEventInEveryModule($actor);

        $events = app(AuditLogQuery::class)->run($actor, 'all', []);

        $this->assertSame(6, $events->total());
    }

    #[Test]
    public function every_preset_is_a_subset_of_all_events(): void
    {
        /*
         * The property that makes a preset safe. A preset narrows; it must never
         * surface something All Events does not, and it must never be the only
         * place an event can be found.
         */
        $actor = $this->personOn(Role::SystemAdmin);
        $this->anEventInEveryModule($actor);

        $query = app(AuditLogQuery::class);

        $everything = collect($query->run($actor, 'all', [])->items())->pluck('id')->all();

        foreach (array_keys((array) config('governance.audit_views')) as $view) {
            $ids = collect($query->run($actor, $view, [])->items())->pluck('id')->all();

            $this->assertEmpty(
                array_diff($ids, $everything),
                "The `{$view}` preset surfaces an event that All Events does not."
            );
        }
    }

    #[Test]
    public function each_preset_produces_the_right_subset(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $this->anEventInEveryModule($actor);

        $query = app(AuditLogQuery::class);

        $modulesIn = static fn (string $view): array => collect($query->run($actor, $view, [])->items())
            ->pluck('module')->unique()->sort()->values()->all();

        $this->assertSame(['Identity'], $modulesIn('administrative'));
        $this->assertSame(['Security'], $modulesIn('security'));
        $this->assertSame(['Governance', 'Platform'], $modulesIn('configuration'));

        /* User Activity filters by action prefix rather than module, because
         * signing in is a Security-module event about a person. */
        $actions = collect($query->run($actor, 'user_activity', [])->items())->pluck('action')->all();

        $this->assertContains('auth.sign_in.succeeded', $actions);
        $this->assertContains('user.disabled', $actions);
        $this->assertNotContains('setting.updated', $actions);
    }

    #[Test]
    public function an_unknown_view_falls_back_to_everything_rather_than_to_nothing(): void
    {
        /*
         * A typo in a URL should show a reader too much and let them notice,
         * not show them an empty trail and let them conclude it is empty. That
         * false-empty reading is the failure SEC-DEC-057 records.
         */
        $actor = $this->personOn(Role::SystemAdmin);
        $this->anEventInEveryModule($actor);

        $this->assertSame(6, app(AuditLogQuery::class)->run($actor, 'not-a-view', [])->total());

        $this->actingAs($actor)
            ->get(route('admin.governance.audit', ['view' => 'not-a-view']))
            ->assertOk()
            ->assertSee('All Events');
    }

    #[Test]
    public function the_network_column_is_not_selected_without_the_permission(): void
    {
        /*
         * NOT SELECTED, not masked. The assertion is about the model instance:
         * if the attribute is absent, the value never reached PHP and cannot
         * leak through a dump, a debug bar or a serialisation.
         */
        $reader = $this->personOn(Role::DomainOwner);
        $this->anEventInEveryModule($this->personOn(Role::SystemAdmin));

        $query = app(AuditLogQuery::class);

        $this->assertFalse($query->maySeeNetwork($reader));
        $this->assertNotContains('ip_address', $query->columnsFor($reader));

        foreach ($query->run($reader, 'all', [])->items() as $event) {
            $this->assertArrayNotHasKey('ip_address', $event->getAttributes());
        }
    }

    #[Test]
    public function a_system_administrator_does_get_the_network_column(): void
    {
        $reader = $this->personOn(Role::SystemAdmin);
        $this->anEventInEveryModule($reader);

        $query = app(AuditLogQuery::class);

        $this->assertTrue($query->maySeeNetwork($reader));
        $this->assertContains('ip_address', $query->columnsFor($reader));
    }

    #[Test]
    public function an_auditor_reads_the_trail_but_not_the_network_identifier(): void
    {
        /*
         * The interaction between D2 and D8. The Auditor capability admits
         * somebody to the trail; it must not hand them network identifiers as a
         * side effect. SEC-DEC-062 and SEC-DEC-063.
         */
        $auditor = $this->personOn(Role::Viewer, auditor: true);
        $this->anEventInEveryModule($this->personOn(Role::SystemAdmin));

        $query = app(AuditLogQuery::class);

        $this->assertSame(6, $query->run($auditor, 'all', [])->total());
        $this->assertFalse($query->maySeeNetwork($auditor));
        $this->assertNotContains('ip_address', $query->columnsFor($auditor));

        $response = $this->actingAs($auditor)->get(route('admin.governance.audit'));

        $response->assertOk();
        $response->assertSee('Network information is not shown');
    }

    #[Test]
    public function a_viewer_without_the_auditor_flag_cannot_reach_the_screen(): void
    {
        $this->actingAs($this->personOn(Role::Viewer))
            ->get(route('admin.governance.audit'))
            ->assertForbidden();
    }

    #[Test]
    public function the_filters_narrow_within_the_selected_view(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $this->anEventInEveryModule($actor);

        $query = app(AuditLogQuery::class);

        $this->assertSame(1, $query->run($actor, 'all', ['outcome' => 'denied'])->total());
        $this->assertSame(2, $query->run($actor, 'all', ['module' => 'Identity'])->total());
        $this->assertSame(1, $query->run($actor, 'all', ['action' => 'retention'])->total());
        /* `actor_label` holds the EMAIL, not the name - `AuditLogger` prefers
         * it because it is the identifier a reader has to hand. Filtering by
         * the name would find nothing, which is what this originally did. */
        $this->assertSame(6, $query->run($actor, 'all', ['actor' => '@example.test'])->total());
        $this->assertSame(6, $query->run($actor, 'all', ['actor' => $actor->email])->total());
        $this->assertSame(0, $query->run($actor, 'all', ['actor' => 'nobody@example.test'])->total());

        /* A filter narrows the VIEW, never widens past it. */
        $this->assertSame(0, $query->run($actor, 'security', ['module' => 'Identity'])->total());
    }

    #[Test]
    public function a_correlation_id_matches_exactly_rather_than_partially(): void
    {
        /*
         * A correlation id is quoted from somewhere else and pasted in whole. A
         * partial match would return one request's events mixed with another's,
         * which during an incident review is worse than returning none.
         */
        $actor = $this->personOn(Role::SystemAdmin);
        $this->anEventInEveryModule($actor);

        $query = app(AuditLogQuery::class);
        $known = $query->run($actor, 'all', [])->items()[0]->correlation_id;

        $this->assertNotSame(0, $query->run($actor, 'all', ['correlation_id' => $known])->total());
        $this->assertSame(0, $query->run($actor, 'all', ['correlation_id' => substr((string) $known, 0, 8)])->total());
    }

    #[Test]
    public function redaction_still_holds_on_the_screen(): void
    {
        /*
         * The trail is redacted at WRITE time, so this asserts the screen does
         * not somehow reconstitute what the redactor removed. A secret-shaped
         * value goes in; `[redacted]` is what the screen can show.
         */
        $actor = $this->personOn(Role::SystemAdmin);
        Auth::login($actor);

        app(AuditLogger::class)->record(
            action: 'security.policy.updated',
            module: 'Security',
            after: ['api_token' => 'sk-live-must-never-appear', 'label' => 'safe value'],
        );

        $response = $this->actingAs($actor)->get(route('admin.governance.audit'));

        $response->assertOk();
        $response->assertDontSee('sk-live-must-never-appear');
    }

    #[Test]
    public function the_screen_is_read_only(): void
    {
        /* An audit screen that could change an audit event would defeat the
         * database triggers that make the trail evidence. */
        foreach (Route::getRoutes() as $route) {
            if ((string) $route->getName() !== 'admin.governance.audit') {
                continue;
            }

            $this->assertSame(['GET', 'HEAD'], $route->methods());
        }
    }

    #[Test]
    public function an_empty_trail_and_an_empty_filter_result_read_differently(): void
    {
        /*
         * "Nothing matches your filter" and "nothing has been recorded" are
         * opposite facts, and showing the second as the first would be alarming
         * on an audit screen.
         */
        $actor = $this->personOn(Role::SystemAdmin);

        $this->actingAs($actor)
            ->get(route('admin.governance.audit'))
            ->assertOk()
            ->assertSee('No events recorded');

        $this->anEventInEveryModule($actor);

        $this->actingAs($actor)
            ->get(route('admin.governance.audit', ['action' => 'nothing-matches-this']))
            ->assertOk()
            ->assertSee('Nothing matches')
            ->assertSee('The trail is not empty');
    }
}
