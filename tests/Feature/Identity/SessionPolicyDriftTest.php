<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EntraTokenFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * D4 - the displayed policy IS the enforced policy, and cannot drift again.
 *
 * The defect this exists for: a constant declaring a 60-minute idle timeout that
 * nothing read, beside a configuration enforcing 120 that nothing checked. If
 * the screen were allowed to write its own number, that disagreement would just
 * move somewhere new and stay invisible for exactly as long.
 *
 * So the enforced value is set to something NOBODY would choose - 37 - and the
 * screen is required to show that. A screen with 60 written into it passes every
 * other test in this unit and fails this one.
 */
final class SessionPolicyDriftTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    protected function setUp(): void
    {
        parent::setUp();

        (new EntraTokenFactory)->configure();
        $this->make = new OrganisationFactory;
    }

    /** Mutation: hardcode 60 in SessionPolicy.jsx or in the controller. */
    public function test_the_screen_shows_the_enforced_value_and_not_the_approved_one(): void
    {
        config(['session.lifetime' => 37]);

        $body = $this->actingAsAdministrator()->get('/console/identity/session-policy')->getContent();

        $this->assertStringContainsString('"idleMinutes":37', $body, 'The screen is not showing the enforced idle timeout.');
        $this->assertStringNotContainsString('"idleMinutes":60', $body);
    }

    /** The absolute lifetime is read from the middleware that enforces it. */
    public function test_the_screen_shows_the_enforced_absolute_lifetime(): void
    {
        $body = $this->actingAsAdministrator()->get('/console/identity/session-policy')->getContent();

        $this->assertStringContainsString(
            '"absoluteHours":'.EnsureSessionIsCurrent::ABSOLUTE_HOURS,
            $body
        );
    }

    /**
     * ...and the page actually RENDERS what it was given.
     *
     * The assertion above proves delivery and nothing more: Inertia embeds its
     * props as JSON, so it passes with the component's render deleted. That
     * exact mistake was made once already in P1-01's confirmation work and found
     * by mutation, so the two halves are separated here from the start.
     *
     * Mutation: stop the component printing policy.idleMinutes.
     */
    public function test_the_page_renders_the_enforced_values(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Identity/SessionPolicy.jsx'));

        $this->assertStringContainsString('{policy.idleMinutes} minutes', $source);
        $this->assertStringContainsString('{policy.absoluteHours} hours', $source);
        $this->assertStringContainsString('{policy.storage}', $source);
    }

    /** Nothing on this screen offers a way to change any of it. */
    public function test_the_screen_offers_no_control(): void
    {
        $body = $this->actingAsAdministrator()->get('/console/identity/session-policy')->getContent();

        foreach (['<input', '<select', '<textarea', 'type=\"submit\"'] as $control) {
            $this->assertStringNotContainsString($control, $body, "The Session Policy screen carries a [{$control}].");
        }
    }

    private function actingAsAdministrator(): self
    {
        $admin = $this->make->user(administrator: true);

        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $admin->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
