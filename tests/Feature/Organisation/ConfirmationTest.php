<?php

declare(strict_types=1);

namespace Tests\Feature\Organisation;

use App\Modules\Organisation\Models\StructureStatus;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use ReflectionMethod;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * A successful write confirms itself.
 *
 * The unit had a refusal channel from the beginning and no success channel at
 * all. On most screens that was invisible, because the change was its own
 * evidence — a row appeared, a status pill flipped. The Company Profile
 * re-renders identically after a save, so there a save that worked and a dead
 * button looked exactly the same, and the Product Owner reported it as "after
 * Click Save nothing happens". The save had worked every time.
 *
 * The important case here is the last one. It asserts that every write is
 * CAPABLE of confirming, rather than that particular writes do — the same shape
 * as the lifecycle-completeness guard, and for the same reason: a write added
 * later with no confirmation would pass every behavioural test in this file,
 * because a test for it would not exist.
 */
final class ConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
    }

    /**
     * The case the Product Owner reported.
     *
     * Mutation: return a plain redirect from ProfileController::update().
     */
    public function test_saving_the_company_profile_confirms_itself(): void
    {
        $organisation = $this->make->organisation();

        $this->actingAsUser($this->make->user($organisation, administrator: true))
            ->put('/console/organisation', ['name' => $organisation->name])
            ->assertSessionHas('confirmation', 'Company Profile saved.');
    }

    /**
     * The screen has to be GIVEN it, not merely the session.
     *
     * This proves delivery and nothing more. Inertia embeds its props as JSON
     * in the page, so this assertion passes on the string being in the payload
     * — it passed with the component's render deleted, found by mutation. What
     * the component does with the prop is the next case's job, and the two
     * together are what the single case pretended to cover.
     */
    public function test_the_confirmation_reaches_the_page_as_a_prop(): void
    {
        $organisation = $this->make->organisation();
        $session = $this->actingAsUser($this->make->user($organisation, administrator: true));

        $session->put('/console/organisation', ['name' => $organisation->name]);

        $response = $session->get('/console/organisation');

        $response->assertOk();
        $this->assertStringContainsString('Company Profile saved.', $response->getContent());
    }

    /**
     * ...and the page renders it, in a polite live region.
     *
     * Read from the component, because the delivery test above cannot see this:
     * a prop sitting unused in the payload is exactly as silent as no prop at
     * all, which is the defect being fixed.
     *
     * `role="status"` rather than `role="alert"`: a success is news, not an
     * interruption, and a screen reader should finish its sentence first.
     *
     * Mutation: replace the render's condition with `false`. CAUGHT here and
     * nowhere else.
     */
    public function test_the_page_renders_the_confirmation_politely(): void
    {
        $source = file_get_contents(resource_path('js/Components/OrganisationPage.jsx'));

        $this->assertMatchesRegularExpression(
            '/\{confirmation && ! ?refusal \? \(/',
            $source,
            'The page does not render the confirmation, or renders it alongside a refusal. A prop '
            .'nobody renders is exactly as silent as no prop at all.'
        );

        $this->assertMatchesRegularExpression(
            '/className="org-confirmation" role="status"/',
            $source,
            'The confirmation is not a polite live region.'
        );

        $this->assertStringContainsString(
            '{confirmation}',
            $source,
            'The confirmation element does not contain the message.'
        );
    }

    /** And only for one render. A confirmation that persists is a banner. */
    public function test_the_confirmation_does_not_survive_the_next_request(): void
    {
        $organisation = $this->make->organisation();
        $session = $this->actingAsUser($this->make->user($organisation, administrator: true));

        $session->put('/console/organisation', ['name' => $organisation->name]);
        $session->get('/console/organisation');

        $this->assertStringNotContainsString(
            'Company Profile saved.',
            $session->get('/console/organisation')->getContent(),
            'The confirmation is still on the page a request later, so it is a banner, not a confirmation.'
        );
    }

    /**
     * A refused write confirms nothing.
     *
     * Mutation: confirm before the try/catch. The screen would then say a
     * deactivation succeeded while showing the refusal that stopped it.
     */
    public function test_a_refused_write_confirms_nothing(): void
    {
        $organisation = $this->make->organisation();
        $unit = $this->make->businessUnit($organisation);
        $this->make->department($unit, 'Engineering');

        $this->actingAsUser($this->make->user($organisation, administrator: true))
            ->patch("/console/organisation/business-units/{$unit->id}/deactivate")
            ->assertSessionMissing('confirmation');

        $this->assertSame(StructureStatus::Active, $unit->fresh()->status);
    }

    /**
     * The Company Profile cannot confirm a save it did not make.
     *
     * Mutation: confirm from the no-organisation early return. This was written
     * that way at first — the patch replaced the guard's redirect rather than
     * the success one — and it would have told a person their profile was saved
     * when there was no profile to save.
     */
    public function test_no_confirmation_when_there_is_no_profile_to_save(): void
    {
        $this->actingAsUser($this->make->user(administrator: true))
            ->put('/console/organisation', ['name' => 'Nothing to update'])
            ->assertSessionMissing('confirmation');
    }

    /** Business language, past tense, and never a record's name. */
    public function test_a_confirmation_carries_no_business_content(): void
    {
        $organisation = $this->make->organisation();
        $session = $this->actingAsUser($this->make->user($organisation, administrator: true));

        $session->post('/console/organisation/business-units', [
            'name' => 'Confidential Unit',
            'code' => 'SECRET',
        ]);

        $confirmation = session('confirmation');

        $this->assertSame('Business unit added.', $confirmation);
        $this->assertStringNotContainsString('Confidential Unit', (string) $confirmation);
        $this->assertStringNotContainsString('SECRET', (string) $confirmation);
    }

    /**
     * Every write can confirm — asserted on the code, not on behaviour.
     *
     * This is the guard. A behavioural test only covers the writes somebody
     * thought to write a test for; a write added next month with a bare
     * redirect would pass every other case in this file, because the case that
     * would fail does not exist. So the controllers are read instead: any
     * method that redirects to an Organisation route after doing work must go
     * through confirm().
     *
     * The one permitted exception is a redirect that follows NO work — the
     * Company Profile's "there is nothing to update" path — and it is named
     * explicitly rather than pattern-matched, so a new bare redirect cannot
     * hide behind the exemption.
     *
     * Mutation: replace any confirm() with a plain redirect. CAUGHT.
     */
    public function test_every_organisation_write_confirms_itself(): void
    {
        $exempt = ['App\Modules\Organisation\Http\Controllers\ProfileController::update'];

        $controllers = glob(app_path('Modules/Organisation/Http/Controllers/*Controller.php'));

        $this->assertNotEmpty($controllers, 'No controllers were found, so this test proves nothing.');

        $checked = 0;

        foreach ($controllers as $file) {
            $class = 'App\Modules\Organisation\Http\Controllers\\'.basename($file, '.php');
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

                if (! str_contains($body, "redirect()->route('organisation")) {
                    continue;
                }

                $checked++;

                $this->assertContains(
                    $class.'::'.$method->getName(),
                    $exempt,
                    "[{$class}::{$method->getName()}] redirects after a write without confirming it. "
                    .'A successful write that says nothing is indistinguishable from a dead button, '
                    .'which is exactly the defect this guard exists for.'
                );
            }
        }

        $this->assertSame(
            1,
            $checked,
            'The set of bare redirects changed. Every Organisation write must confirm itself; only '
            .'the Company Profile path that updates nothing is exempt.'
        );
    }

    /** And every confirm() call names a message somebody wrote. */
    public function test_no_confirmation_is_empty(): void
    {
        $found = 0;

        foreach (glob(app_path('Modules/Organisation/Http/Controllers/*Controller.php')) as $file) {
            preg_match_all("/\\\$this->confirm\('([^']+)', '([^']*)'/", file_get_contents($file), $calls, PREG_SET_ORDER);

            foreach ($calls as [, $route, $message]) {
                $found++;

                $this->assertNotSame('', trim($message), "The confirmation for [{$route}] is empty.");
                $this->assertStringEndsWith('.', $message, "The confirmation for [{$route}] is not a sentence.");
            }
        }

        $this->assertGreaterThanOrEqual(20, $found, 'Too few confirmations were found to prove anything.');
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
