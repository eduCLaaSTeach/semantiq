<?php

declare(strict_types=1);

namespace Tests\Feature\People;

use App\Modules\People\Services\GroupService;
use App\Modules\People\Services\UserDirectoryService;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use Tests\Support\OrganisationFactory;
use Tests\Support\PeopleFactory;
use Tests\TestCase;

/**
 * Negative cases 16, 17 and 18 - the PRESENCE guards.
 *
 * P1-01 shipped, passed CI and reached the Product Owner with Update missing on
 * four of its five scoped entities. Nothing failed, because nothing was looking:
 * every test asserted that the operations which existed behaved correctly, and
 * AN OPERATION THAT DOES NOT EXIST HAS NO TEST TO FAIL.
 *
 * PLAN §5 and §6 name every P1-03 operation individually - the word "CRUD" does
 * not appear in that plan - precisely so this file could be written before the
 * implementation rather than after the report.
 */
final class PeopleCompletenessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PLAN §5 and §6, transcribed. Verb, then URI suffix after the collection
     * root; '' is the collection and {} stands for the record.
     *
     * @var array<string, array<string, array{string, string}>>
     */
    private const LIFECYCLE = [
        'console/people/users' => [
            'read the list' => ['GET', ''],
            'read one' => ['GET', '/{}'],
            'create' => ['POST', ''],
            'assign or change organisation' => ['PUT', '/{}'],
            'deactivate' => ['PATCH', '/{}/deactivate'],
            'activate' => ['PATCH', '/{}/reactivate'],
            'reveal an identifier' => ['POST', '/{}/reveal'],
            'guarded purge' => ['DELETE', '/{}'],
        ],
        'console/people/groups' => [
            'read the list' => ['GET', ''],
            'read one' => ['GET', '/{}'],
            'create' => ['POST', ''],
            'edit' => ['PUT', '/{}'],
            'deactivate' => ['PATCH', '/{}/deactivate'],
            'activate' => ['PATCH', '/{}/reactivate'],
            'guarded purge' => ['DELETE', '/{}'],
            'add a member' => ['POST', '/{}/members'],
            'end a membership' => ['PATCH', '/{}/members/{}/remove'],
        ],
    ];

    /**
     * Negative case 17, first half. EVERY OPERATION PLAN §5 AND §6 NAMES IS
     * REACHABLE.
     *
     * Mutation: delete one route from routes/web.php.
     */
    public function test_every_named_operation_has_a_route(): void
    {
        $signatures = [];

        foreach (Route::getRoutes() as $route) {
            $uri = preg_replace('/\{[^}]+\}/', '{}', $route->uri());

            foreach ($route->methods() as $method) {
                $signatures[] = $method.' '.$uri;
            }
        }

        foreach (self::LIFECYCLE as $root => $operations) {
            foreach ($operations as $operation => [$verb, $suffix]) {
                $this->assertContains(
                    $verb.' '.$root.$suffix,
                    $signatures,
                    "[{$root}] has no way to {$operation}. PLAN §5 and §6 name every P1-03 operation "
                    .'individually so that a missing one cannot pass unnoticed the way Update did in '
                    .'P1-01.'
                );
            }
        }
    }

    /**
     * Negative case 17, second half. AND EVERY OPERATION IS A SERVICE METHOD.
     *
     * A route with no service behind it would be a controller doing lifecycle
     * work inline, which is where the guards stop being reusable.
     *
     * Mutation: delete a service method and inline it in the controller.
     */
    public function test_every_named_operation_is_a_service_method(): void
    {
        $expected = [
            UserDirectoryService::class => [
                'provision', 'deactivate', 'reactivate', 'assignOrganisation', 'purge',
                'isPurgeable', 'currentRelationshipPhrases',
            ],
            GroupService::class => [
                'create', 'update', 'deactivate', 'reactivate', 'purge', 'isPurgeable',
                'addMember', 'removeMember',
            ],
        ];

        foreach ($expected as $service => $methods) {
            foreach ($methods as $method) {
                $this->assertTrue(
                    method_exists($service, $method),
                    "[{$service}::{$method}()] does not exist. PLAN names it as a required operation."
                );
            }
        }
    }

    /**
     * Negative case 16. THE APPLICATION'S DELETE ROUTES ARE EXACTLY SEVEN.
     *
     * Four are D-24's master types; P1-03 added two, and P1-04 adds one - the
     * guarded purge of a custom domain nobody has ever been accountable for.
     * The whole set is asserted as an EQUALITY. A subset check would pass while
     * an eighth appeared - on a membership or an ownership period, say, whose
     * history the units exist to keep.
     *
     * This is also why clearing a domain's owner is a PATCH and not a DELETE:
     * in this codebase DELETE means a record is permanently destroyed, and
     * clearing an owner ends a period and destroys nothing. Spelling it as a
     * DELETE would both misdescribe it and weaken this assertion.
     *
     * Mutation: add a DELETE for a membership, an ownership period, or an
     * organisation.
     */
    public function test_the_application_has_exactly_seven_delete_routes(): void
    {
        $registered = collect(Route::getRoutes())
            ->filter(fn ($route): bool => in_array('DELETE', $route->methods(), true))
            ->map(fn ($route): string => $route->uri())
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            [
                'console/domains/{domain}',
                'console/organisation/business-units/{businessUnit}',
                'console/organisation/departments/{department}',
                'console/organisation/legal-entities/{legalEntity}',
                'console/organisation/teams/{team}',
                'console/people/groups/{group}',
                'console/people/users/{user}',
            ],
            $registered,
            'The set of DELETE routes is not the seven the design permits. Permanent deletion reaches '
            .'four D-24 master types, a group with no membership history, a person who has never '
            .'signed in, and a custom domain nobody has ever been accountable for - and nothing '
            .'else, ever.'
        );
    }

    /**
     * Negative case 18. EVERY PEOPLE WRITE CONFIRMS ITSELF.
     *
     * The P1-01 guard, extended to this module. A successful write that says
     * nothing is indistinguishable from a dead button, which is exactly how the
     * Company Profile save was reported as broken while working every time.
     *
     * Asserted as a CAPABILITY over every controller method, so a write added
     * later with no confirmation fails here rather than waiting for somebody to
     * write a test for it.
     *
     * Mutation: return a bare redirect from any People write.
     */
    public function test_every_people_write_confirms_itself(): void
    {
        $checked = 0;

        foreach (glob(app_path('Modules/People/Http/Controllers/*Controller.php')) as $file) {
            $class = 'App\Modules\People\Http\Controllers\\'.basename($file, '.php');
            $source = file($file);

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class !== $class) {
                    continue;
                }

                $body = implode('', array_slice(
                    $source,
                    $method->getStartLine() - 1,
                    $method->getEndLine() - $method->getStartLine() + 1
                ));

                if (! str_contains($body, 'RedirectResponse')) {
                    continue;
                }

                $checked++;

                $this->assertStringContainsString(
                    '$this->confirm(',
                    $body,
                    "[{$class}::{$method->getName()}] redirects after a write without confirming it."
                );

                $this->assertStringNotContainsString(
                    'return redirect()',
                    $body,
                    "[{$class}::{$method->getName()}] returns a bare redirect."
                );
            }
        }

        // Twelve write methods; reveal is not one, because it answers with a
        // value rather than redirecting.
        $this->assertSame(
            12,
            $checked,
            'The number of People write routes changed. Every one of them must confirm itself.'
        );
    }

    /** And every confirmation is a sentence somebody wrote, carrying no business content. */
    public function test_every_confirmation_is_a_sentence_and_names_nobody(): void
    {
        $found = 0;

        foreach (glob(app_path('Modules/People/Http/Controllers/*Controller.php')) as $file) {
            preg_match_all("/\\\$this->confirm\('([^']+)', '([^']*)'/", file_get_contents($file), $calls, PREG_SET_ORDER);

            foreach ($calls as [, $route, $message]) {
                $found++;

                $this->assertNotSame('', trim($message), "The confirmation for [{$route}] is empty.");
                $this->assertStringEndsWith('.', $message, "The confirmation for [{$route}] is not a sentence.");

                // Past tense, and never a person's or a group's name - a name is
                // business content, and this is the channel a refusal uses too.
                $this->assertDoesNotMatchRegularExpression(
                    '/\$/',
                    $message,
                    "The confirmation for [{$route}] interpolates a value, so it can carry a name."
                );
            }
        }

        $this->assertSame(12, $found, 'The number of confirmations changed.');
    }

    /**
     * And they confirm in practice, not only in the source.
     *
     * The source guard above proves every write CAN confirm. This proves the
     * confirmation actually reaches the screen as a prop, which is the half that
     * failed in P1-01: the session had it and the page never saw it.
     */
    public function test_a_people_write_reaches_the_screen_as_a_confirmation(): void
    {
        $make = new OrganisationFactory;
        $people = new PeopleFactory;

        $organisation = $make->organisation();
        $admin = $make->user($organisation, administrator: true);

        $group = $people->group($organisation);

        $this->actingAsUser($admin)
            ->put("/console/people/groups/{$group->id}", ['name' => 'Renamed'])
            ->assertSessionHas('confirmation', 'Group saved.');

        $props = $this->actingAsUser($admin)
            ->withSession(['confirmation' => 'Group saved.'])
            ->get("/console/people/groups/{$group->id}")
            ->viewData('page')['props'];

        $this->assertSame(
            'Group saved.',
            $props['confirmation'] ?? null,
            'The confirmation reached the session and not the screen, which is the P1-01 defect '
            .'exactly: the save worked and the administrator was told nothing.'
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
