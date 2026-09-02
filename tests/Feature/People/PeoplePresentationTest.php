<?php

declare(strict_types=1);

namespace Tests\Feature\People;

use App\Modules\People\Models\Group;
use App\Modules\People\Models\GroupMembership;
use App\Modules\People\Models\GroupStatus;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\OrganisationFactory;
use Tests\Support\PeopleFactory;
use Tests\Support\ScreenSource;
use Tests\TestCase;

/**
 * What the screens are allowed to carry, and what the log is allowed to know.
 *
 * The privacy rules here are P1-02's, reused rather than reinvented: a full
 * Object ID is an identifier for a real person in a real directory, and a page
 * payload is not a safe place for it. P1-03 puts one on screen for every person
 * in the organisation instead of one for the deployment, so the same rule now
 * applies many more times.
 */
final class PeoplePresentationTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private PeopleFactory $people;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->people = new PeopleFactory;

        config()->set('identity.microsoft.tenant_id', '99999999-9999-9999-9999-999999999999');
    }

    /**
     * Negative case 34. THE FULL OBJECT ID AND TENANT ARE ABSENT FROM EVERY PAGE
     * PAYLOAD.
     *
     * Not masked in CSS - absent. A value that reaches the browser has reached
     * the browser, whatever is drawn over it.
     *
     * Asserted against the RAW RESPONSE BODY of every People screen, not against
     * the props array: a value could arrive through a shared prop, a flash
     * message or an error bag, none of which the props check would see.
     *
     * Mutation: send them as props and mask in CSS.
     */
    public function test_no_people_screen_carries_a_full_object_id_or_tenant(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->actingAsUser($admin)->post('/console/people/users', [
            'object_id' => '3f2504e0-4f89-11d3-9a0c-0305e82c3301',
            'email' => 'watched@example.test',
        ]);

        $person = User::query()->where('email', 'watched@example.test')->sole();

        $group = $this->people->group($organisation);
        $this->people->membership($group, $person);

        foreach ([
            '/console/people/users',
            "/console/people/users/{$person->id}",
            '/console/people/groups',
            "/console/people/groups/{$group->id}",
            '/console',
        ] as $uri) {
            $body = $this->actingAsUser($admin)->get($uri)->getContent();

            $this->assertStringNotContainsString(
                $person->external_subject,
                $body,
                "[{$uri}] carries a full Object ID."
            );

            $this->assertStringNotContainsString(
                (string) config('identity.microsoft.tenant_id'),
                $body,
                "[{$uri}] carries the full tenant identifier."
            );
        }

        // And the record page does carry the mask, so this is testing a screen
        // that shows the value rather than one that omits the row.
        $props = $this->actingAsUser($admin)
            ->get("/console/people/users/{$person->id}")
            ->viewData('page')['props'];

        $this->assertNotEmpty($props['person']['objectIdMasked']);
        $this->assertNotSame($person->external_subject, $props['person']['objectIdMasked']);
        $this->assertStringContainsString('…', $props['person']['objectIdMasked']);
    }

    /**
     * Negative case 35. Reveal accepts EXACTLY TWO field names.
     *
     * D-37, the P1-02 pattern reused rather than a second reveal mechanism. The
     * refusal is identical for a field that does not exist and for one that
     * exists but is not revealable, so the endpoint cannot be used to enumerate
     * the columns of the users table.
     *
     * Mutation: accept a third field.
     */
    public function test_reveal_accepts_exactly_two_field_names(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        foreach (['object_id' => $person->external_subject, 'tenant' => $person->tenant_id] as $field => $expected) {
            $response = $this->actingAsUser($admin)
                ->postJson("/console/people/users/{$person->id}/reveal", ['field' => $field]);

            $response->assertOk();
            $this->assertSame($expected, $response->json('value'));
        }

        foreach ([
            'email', 'display_name', 'platform_role', 'status', 'id', 'organisation_id',
            'password', 'external_subject', 'tenant_id', 'provider', '', null, '*',
        ] as $field) {
            $response = $this->actingAsUser($admin)
                ->postJson("/console/people/users/{$person->id}/reveal", ['field' => $field]);

            $response->assertStatus(422);

            $this->assertNull($response->json('value'), "[{$field}] was revealed.");

            $this->assertSame(
                'That cannot be revealed.',
                $response->json('message'),
                'The refusal differs by field, which turns this endpoint into a way of asking which '
                .'columns exist.'
            );
        }
    }

    /** Reveal is POST, so it is CSRF-protected and not triggerable by a third-party page. */
    public function test_reveal_is_not_reachable_by_a_get(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $person = $this->make->user($organisation);
        $person->forceFill(['external_subject' => 'aaaaaaaa-1234-5678-9abc-deadbeef0001'])->save();

        // Asserted with debug OFF, which is production's setting. The debug
        // error page renders the whole request context - query bindings
        // included - so leaving it on would fail this for a reason that has
        // nothing to do with the route, and would have hidden the real question.
        config()->set('app.debug', false);

        $response = $this->actingAsUser($admin)
            ->get("/console/people/users/{$person->id}/reveal?field=object_id");

        $this->assertSame(
            405,
            $response->status(),
            'Reveal answered a GET. A GET that returns a value is triggerable by any third-party '
            .'page the administrator happens to be visiting, because it carries their session and '
            .'no CSRF token is involved.'
        );

        $this->assertStringNotContainsString($person->external_subject, $response->getContent());
    }

    /**
     * Negative case 36. NO SECURITY EVENT CARRIES AN EMAIL, A NAME OR AN OBJECT
     * ID.
     *
     * D-12 fixes the permitted context keys, and P1-03 adds no new ones. This
     * exercises every P1-03 event through its real route and reads what actually
     * reached the log.
     *
     * Mutation: add 'email' to a recorded context.
     */
    public function test_no_people_event_carries_a_personal_identifier(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $recorded = [];

        Log::listen(function ($message) use (&$recorded): void {
            $recorded[] = $message;
        });

        // Every P1-03 write, through its route.
        $this->actingAsUser($admin)->post('/console/people/users', [
            'object_id' => '3f2504e0-4f89-11d3-9a0c-0305e82c3301',
            'email' => 'logged.person@example.test',
            'display_name' => 'Logged Person',
        ]);

        $person = User::query()->where('email', 'logged.person@example.test')->sole();

        $this->actingAsUser($admin)->post('/console/people/groups', [
            'name' => 'Logged Group',
            'code' => 'LG',
            'description' => 'A group whose name must not reach the log.',
        ]);

        $group = Group::query()->where('name', 'Logged Group')->sole();

        $this->actingAsUser($admin)->post("/console/people/groups/{$group->id}/members", ['user_id' => $person->id]);

        $membership = GroupMembership::query()->sole();

        $this->actingAsUser($admin)->patch("/console/people/groups/{$group->id}/members/{$membership->id}/remove");
        $this->actingAsUser($admin)->put("/console/people/groups/{$group->id}", ['name' => 'Renamed Group']);
        $this->actingAsUser($admin)->patch("/console/people/groups/{$group->id}/deactivate");
        $this->actingAsUser($admin)->patch("/console/people/groups/{$group->id}/reactivate");
        $this->actingAsUser($admin)->patch("/console/people/users/{$person->id}/deactivate");
        $this->actingAsUser($admin)->patch("/console/people/users/{$person->id}/reactivate");
        $this->actingAsUser($admin)->put("/console/people/users/{$person->id}", ['organisation_id' => $organisation->id]);

        // A refusal too, because a refusal is the tempting place to explain
        // WHICH person was already there.
        $this->actingAsUser($admin)->post('/console/people/users', [
            'object_id' => '3f2504e0-4f89-11d3-9a0c-0305e82c3301',
            'email' => 'duplicate@example.test',
        ]);

        $this->assertGreaterThanOrEqual(
            10,
            count($recorded),
            'Too few events were recorded for this guard to mean anything.'
        );

        $forbidden = [
            'logged.person@example.test', 'Logged Person', 'duplicate@example.test',
            '3f2504e0-4f89-11d3-9a0c-0305e82c3301', '99999999-9999-9999-9999-999999999999',
            'Logged Group', 'Renamed Group',
        ];

        foreach ($recorded as $message) {
            $rendered = $message->message.' '.json_encode($message->context);

            foreach ($forbidden as $identifier) {
                $this->assertStringNotContainsString(
                    $identifier,
                    $rendered,
                    "A security event carried [{$identifier}]: {$rendered}"
                );
            }

            // And the keys are still exactly D-12's, so a new one cannot arrive
            // carrying something this list did not think to forbid.
            foreach (array_keys($message->context['context'] ?? []) as $key) {
                $this->assertContains(
                    $key,
                    SecurityEventLogger::ALLOWED_KEYS,
                    "A security event carried the context key [{$key}], which D-12 does not permit."
                );
            }
        }
    }

    /**
     * Negative case 37. Directory-owned fields are READ-ONLY, and the screen
     * says why.
     *
     * The behavioural half is in UserProvisioningTest. This is the visible half:
     * an administrator must be told the field belongs to Microsoft, not left
     * hunting for an edit control that does not exist.
     *
     * Mutation: make email editable.
     */
    public function test_the_directory_fields_are_presented_as_read_only(): void
    {
        $source = ScreenSource::rendered('Pages/People/User.jsx');

        $this->assertStringContainsString('From Microsoft Entra', $source);
        $this->assertStringContainsString('cannot be changed here', $source);

        // No input bound to a directory-owned field.
        foreach (['email', 'display_name', 'external_subject', 'tenant_id', 'provider'] as $field) {
            $this->assertStringNotContainsString(
                "setData('{$field}'",
                $source,
                "The record screen offers an editor for [{$field}], which Microsoft owns and a "
                .'sign-in silently overwrites.'
            );
        }
    }

    /**
     * Negative case 38. SEARCH, FILTER AND PAGINATION WORK AGAINST SEEDED
     * VOLUME.
     *
     * At acceptance production will hold two or three people, so these are
     * exercised there and not stressed. Sixty are seeded here so that a page
     * limit that was ignored, or a filter that matched everything, actually
     * shows.
     *
     * Mutation: remove the paginate() limit; ignore the status filter.
     */
    public function test_search_filter_and_pagination_work_against_volume(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $group = $this->people->group($organisation, 'Finance');

        for ($i = 0; $i < 60; $i++) {
            $person = $this->make->user(
                $organisation,
                status: $i % 3 === 0 ? UserStatus::Inactive : UserStatus::Active,
            );

            $person->forceFill([
                'display_name' => sprintf('Person %02d %s', $i, $i % 2 === 0 ? 'Alpha' : 'Beta'),
                'email' => sprintf('person%02d@example.test', $i),
            ])->save();

            if ($i % 5 === 0) {
                $this->people->membership($group, $person);
            }
        }

        $page = fn (array $query): array => $this->actingAsUser($admin)
            ->get('/console/people/users?'.http_build_query($query))
            ->viewData('page')['props']['users'];

        // Pagination: the page is bounded, and the total is not.
        $first = $page([]);

        $this->assertCount(25, $first['data'], 'The page is not limited to 25 rows.');
        $this->assertSame(61, $first['total'], 'The total does not count everybody.');
        $this->assertSame(3, $first['lastPage']);

        $second = $page(['page' => 2]);

        $this->assertCount(25, $second['data']);

        $this->assertSame(
            [],
            array_intersect(array_column($first['data'], 'id'), array_column($second['data'], 'id')),
            'Page two repeats rows from page one.'
        );

        // Search: fewer than everybody, and every row matches.
        $alpha = $page(['search' => 'Alpha']);

        $this->assertSame(30, $alpha['total']);

        foreach ($alpha['data'] as $row) {
            $this->assertStringContainsString('Alpha', $row['name']);
        }

        // Search reaches email as well as name.
        $byEmail = $page(['search' => 'person07@']);

        $this->assertSame(1, $byEmail['total']);

        // Status filter.
        $inactive = $page(['status' => 'inactive']);

        $this->assertSame(20, $inactive['total']);

        foreach ($inactive['data'] as $row) {
            $this->assertSame('inactive', $row['status']);
        }

        // Group filter.
        $inGroup = $page(['group' => $group->id]);

        $this->assertSame(12, $inGroup['total']);

        // Organisation filter, which only means anything because unassigned
        // people are in this list at all.
        $unassigned = $this->make->user();
        $unassigned->forceFill(['display_name' => 'Unassigned Alpha'])->save();

        $this->assertSame(1, $page(['organisation' => 'unassigned'])['total']);
        $this->assertSame(61, $page(['organisation' => 'assigned'])['total']);

        // Filters combine rather than replace one another.
        $combined = $page(['search' => 'Alpha', 'status' => 'active', 'group' => $group->id]);

        $this->assertLessThan($inGroup['total'], $combined['total']);
        $this->assertGreaterThan(0, $combined['total']);

        foreach ($combined['data'] as $row) {
            $this->assertSame('active', $row['status']);
            $this->assertStringContainsString('Alpha', $row['name']);
        }
    }

    /** The same three, on the Groups list and on a group's members. */
    public function test_the_group_screens_search_filter_and_paginate(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        for ($i = 0; $i < 40; $i++) {
            $this->people->group(
                $organisation,
                sprintf('Group %02d %s', $i, $i % 2 === 0 ? 'Alpha' : 'Beta'),
                $i % 4 === 0 ? GroupStatus::Inactive : GroupStatus::Active,
                sprintf('G%02d', $i),
            );
        }

        $groups = fn (array $query): array => $this->actingAsUser($admin)
            ->get('/console/people/groups?'.http_build_query($query))
            ->viewData('page')['props']['groups'];

        $this->assertCount(25, $groups([])['data']);
        $this->assertSame(40, $groups([])['total']);
        $this->assertCount(15, $groups(['page' => 2])['data']);
        $this->assertSame(20, $groups(['search' => 'Alpha'])['total']);
        $this->assertSame(10, $groups(['status' => 'inactive'])['total']);
        $this->assertSame(1, $groups(['search' => 'G07'])['total']);

        // A group's members.
        $group = $this->people->group($organisation, 'Crowded');

        for ($i = 0; $i < 30; $i++) {
            $person = $this->make->user($organisation);
            $person->forceFill([
                'display_name' => sprintf('Member %02d %s', $i, $i % 2 === 0 ? 'Alpha' : 'Beta'),
            ])->save();

            $this->people->membership(
                $group,
                $person,
                now()->subDays(60 - $i),
                $i % 3 === 0 ? now()->subDays(30 - ($i / 3)) : null,
            );
        }

        $members = fn (array $query): array => $this->actingAsUser($admin)
            ->get("/console/people/groups/{$group->id}?".http_build_query($query))
            ->viewData('page')['props']['members'];

        $this->assertCount(25, $members([])['data']);
        $this->assertSame(30, $members([])['total']);
        $this->assertSame(10, $members(['period' => 'past'])['total']);
        $this->assertSame(20, $members(['period' => 'current'])['total']);
        $this->assertSame(15, $members(['search' => 'Alpha'])['total']);

        foreach ($members(['period' => 'past'])['data'] as $row) {
            $this->assertFalse($row['current']);
            $this->assertNotNull($row['leftAt']);
        }
    }

    /**
     * An empty RESULT and an empty GROUP are different facts, and the screen
     * must not state the wrong one.
     *
     * A group with history whose filter matches nobody must not say "Nobody has
     * ever been in this group", which would be false.
     */
    public function test_an_empty_search_result_is_not_reported_as_an_empty_group(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $empty = $this->people->group($organisation, 'Empty');
        $used = $this->people->group($organisation, 'Used');

        $this->people->membership($used, $person, now()->subYear(), now()->subMonth());

        $props = fn (int $id, array $query = []): array => $this->actingAsUser($admin)
            ->get("/console/people/groups/{$id}?".http_build_query($query))
            ->viewData('page')['props'];

        $this->assertFalse($props($empty->id)['everHadMembers']);
        $this->assertTrue($props($used->id)['everHadMembers']);

        // A filter that matches nobody does not turn a used group into an empty one.
        $filtered = $props($used->id, ['search' => 'nobody-by-this-name']);

        $this->assertSame([], $filtered['members']['data']);
        $this->assertTrue(
            $filtered['everHadMembers'],
            'A search that matched nobody would make the screen say nobody has ever been in this '
            .'group, which is untrue and destroys the only reason the history is kept.'
        );
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
